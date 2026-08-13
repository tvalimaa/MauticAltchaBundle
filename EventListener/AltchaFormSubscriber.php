<?php declare(strict_types=1);

namespace MauticPlugin\MauticAltchaBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\ValidationEvent;
use Mautic\FormBundle\FormEvents;

use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\LeadEvents;

use Mautic\PluginBundle\Helper\IntegrationHelper;
use Mautic\PluginBundle\Integration\AbstractIntegration;

use MauticPlugin\MauticAltchaBundle\Form\Type\AltchaType;
use MauticPlugin\MauticAltchaBundle\Integration\AltchaIntegration;
use MauticPlugin\MauticAltchaBundle\Service\AltchaClient;
use MauticPlugin\MauticAltchaBundle\CaptchaEvents;

/**
 * <h1>Class AltchaFormSubscriber</h1>
 *
 * @package MauticPlugin\MauticAltchaBundle\EventListener
 *
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
class AltchaFormSubscriber implements EventSubscriberInterface {

    public const MODEL_NAME_KEY_LEAD = "lead.lead";

    private ?TranslatorInterface $translator = null;

    private bool $isConfigured = false;

    /**
     * <h2>AltchaFormSubscriber constructor.</h2>
     *
     * @param EventDispatcherInterface $eventDispatcher
     * @param AltchaClient             $altchaClient
     * @param LeadModel                $leadModel
     * @param RequestStack             $requestStack
     * @param IntegrationHelper        $integrationHelper
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AltchaClient              $altchaClient,
        private readonly LeadModel                 $leadModel,
        private readonly RequestStack              $requestStack,

        IntegrationHelper $integrationHelper
    ) {
        $integrationObject = $integrationHelper->getIntegrationObject(AltchaIntegration::INTEGRATION_NAME);

        if($integrationObject instanceof AbstractIntegration) {
            $this->translator = $integrationObject->getTranslator();
        }

        $this->isConfigured = $this->altchaClient->isConfigured();
    }

    /** {@inheritDoc} */
    public static function getSubscribedEvents(): array {
        return [
            FormEvents::FORM_ON_BUILD              => ["onFormBuild", 0],
            CaptchaEvents::ALTCHA_ON_FORM_VALIDATE => ["onFormValidate", 0]
        ];
    }

    /**
     * <h2>onFormBuild</h2>
     *
     * Adds the "ALTCHA" field type to the form builder palette and, whenever
     * the form is actually rendered, generates a brand new signed challenge
     * for the widget to solve.
     *
     * @param FormBuilderEvent $event
     *
     * @return void
     */
    public function onFormBuild(FormBuilderEvent $event): void {
        if(!$this->isConfigured)
            return;

        $event->addFormField("plugin.altcha", [
            "label"    => "strings.altcha.plugin.name",
            "formType" => AltchaType::class,
            "template" => "@MauticAltcha/Integration/altcha.html.twig",

            "builderOptions" => [
                "addLeadFieldList" => false,
                "addIsRequired"    => false,
                "addDefaultValue"  => false,
                "addSaveResult"    => false
            ]
        ]);

        $event->addValidator("plugin.altcha.validator", [
            "eventName" => CaptchaEvents::ALTCHA_ON_FORM_VALIDATE,
            "fieldType" => "plugin.altcha"
        ]);
    }

    /**
     * <h2>onFormValidate</h2>
     *
     * Verifies the base64 payload the <altcha-widget> posts in its hidden
     * "altcha" input. Verification happens entirely locally (HMAC signature
     * + expiry + proof-of-work check) - no outbound HTTP request is made.
     *
     * @param ValidationEvent $event
     *
     * @return void
     */
    public function onFormValidate(ValidationEvent $event): void {
        if(!$this->isConfigured)
            return;

        $payload = (string) $this->requestStack->getCurrentRequest()?->request->get("altcha", "");

        if($this->altchaClient->verify($payload))
            return;

        $event->failedValidation($this->translator === null ? "ALTCHA verification was not successful." : $this->translator->trans("strings.altcha.failure_message"));

        // Mirror the other CAPTCHA providers: if a lead was nonetheless
        // created for this failed submission, remove it once the request
        // finishes so no spam contacts pile up in the database.
        $this->eventDispatcher->addListener(LeadEvents::LEAD_POST_SAVE, function(LeadEvent $event) {
            if(!$event->isNew())
                return;

            $lead = $event->getLead();

            $this->eventDispatcher->addListener("kernel.terminate", function() use ($lead) {
                if($lead)
                    $this->leadModel->deleteEntity($lead);
            });
        }, -255);
    }

}
