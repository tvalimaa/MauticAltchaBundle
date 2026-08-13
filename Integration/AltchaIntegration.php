<?php declare(strict_types=1);

namespace MauticPlugin\MauticAltchaBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

/**
 * <h1>Class AltchaIntegration</h1>
 *
 * ALTCHA is self-hosted by default: there is no third-party API to
 * authenticate against, so authentication type is "none". Two ways of
 * running it are supported side by side in the same settings form:
 *
 * - Self-hosted: just an "hmac_secret" that Mautic uses to sign/verify
 *   challenges on its own, with no outbound requests at all.
 * - ALTCHA Sentinel: a "sentinel_domain" (your Sentinel instance's base
 *   URL), a "sentinel_api_key", and a "sentinel_api_secret". Sentinel
 *   issues challenges directly to the visitor's browser; Mautic only
 *   verifies the resulting server signature, which is still a local check
 *   (see {@see \MauticPlugin\MauticAltchaBundle\Service\AltchaClient}).
 *
 * IMPORTANT: none of these four fields are added via getRequiredKeyFields().
 * That method's name is literal - Mautic renders every field it returns as
 * mandatory (both an HTML "required" attribute and, in some versions, a
 * NotBlank-style check), which would make it impossible to save the form
 * with only the self-hosted secret filled in (or only the Sentinel fields).
 * Since no single field here is *always* needed - only "one full set or
 * the other" - all four are instead added as plain, optional fields via
 * appendToForm() for the "keys" form area, each with 'required' => false.
 * {@see isConfigured()} enforces the actual "one set or the other" rule at
 * runtime instead.
 *
 * Every method overridden here declares an explicit return type matching
 * AbstractIntegration's own current (typed) signatures - PHP treats
 * omitting a return type in a child method as a fatal error if the parent
 * declares one, so these aren't cosmetic.
 *
 * @package MauticPlugin\MauticAltchaBundle\Integration
 *
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
class AltchaIntegration extends AbstractIntegration {

    public const INTEGRATION_NAME = "Altcha";

    /** {@inheritDoc} */
    public function getName(): string {
        return self::INTEGRATION_NAME;
    }

    /** {@inheritDoc} */
    public function getDisplayName(): string {
        return "ALTCHA";
    }

    /** {@inheritDoc} */
    public function getAuthenticationType(): string {
        return "none";
    }

    /**
     * <h2>getRequiredKeyFields</h2>
     *
     * Deliberately empty - see the class docblock. The four credential
     * fields are added manually via {@see appendToForm()} instead, so none
     * of them are forced to be non-empty simultaneously.
     *
     * @return array
     */
    public function getRequiredKeyFields(): array {
        return [];
    }

    /** {@inheritDoc} */
    public function getSecretKeys(): array {
        return [
            "hmac_secret",
            "sentinel_api_secret"
        ];
    }

    /**
     * <h2>isConfigured</h2>
     *
     * Overridden because none of the four key fields are simultaneously
     * required - the default implementation from AbstractIntegration would
     * demand ALL of them be filled in at once (and since we return an empty
     * getRequiredKeyFields(), the default implementation would otherwise
     * trivially always return true regardless of what's actually filled
     * in). Here, either the self-hosted secret OR the full set of Sentinel
     * credentials is required to count as configured.
     *
     * @return bool
     */
    public function isConfigured(): bool {
        $keys = $this->getKeys();

        $sentinelReady = !empty($keys["sentinel_domain"]) && !empty($keys["sentinel_api_key"]) && !empty($keys["sentinel_api_secret"]);
        $selfHostedReady = !empty($keys["hmac_secret"]);

        return $sentinelReady || $selfHostedReady;
    }

    /**
     * <h2>appendToForm</h2>
     *
     * Adds the four credential fields to the "keys" tab as plain optional
     * fields (see the class docblock for why they can't go through
     * getRequiredKeyFields() instead).
     *
     * @param mixed  $builder
     * @param array  $data
     * @param string $formArea
     *
     * @return void
     */
    public function appendToForm(&$builder, $data, $formArea): void {
        if("keys" !== $formArea)
            return;

        $builder->add("hmac_secret", PasswordType::class, [
            "label"    => "strings.altcha.settings.hmac_secret",
            "required" => false,
            "data"     => $data["hmac_secret"] ?? "",

            "label_attr" => [
                "class" => "control-label"
            ],

            "attr" => [
                "class"       => "form-control",
                "tooltip"     => "strings.altcha.settings.hmac_secret.notice",
                "placeholder" => "strings.altcha.settings.hmac_secret.placeholder"
            ]
        ])->add("sentinel_domain", UrlType::class, [
            "label"    => "strings.altcha.settings.sentinel_domain",
            "required" => false,
            "data"     => $data["sentinel_domain"] ?? "",

            "label_attr" => [
                "class" => "control-label"
            ],

            "attr" => [
                "class"   => "form-control",
                "tooltip" => "strings.altcha.settings.sentinel_domain.notice"
            ]
        ])->add("sentinel_api_key", TextType::class, [
            "label"    => "strings.altcha.settings.sentinel_api_key",
            "required" => false,
            "data"     => $data["sentinel_api_key"] ?? "",

            "label_attr" => [
                "class" => "control-label"
            ],

            "attr" => [
                "class"   => "form-control",
                "tooltip" => "strings.altcha.settings.sentinel_api_key.notice"
            ]
        ])->add("sentinel_api_secret", PasswordType::class, [
            "label"    => "strings.altcha.settings.sentinel_api_secret",
            "required" => false,
            "data"     => $data["sentinel_api_secret"] ?? "",

            "label_attr" => [
                "class" => "control-label"
            ],

            "attr" => [
                "class"       => "form-control",
                "tooltip"     => "strings.altcha.settings.sentinel_api_secret.notice",
                "placeholder" => "strings.altcha.settings.sentinel_api_secret.placeholder"
            ]
        ]);
    }

    /** {@inheritDoc} */
    public function getFormNotes($section): array {
        if(in_array($section, ["keys", "custom"], true)) {
            return [
                "strings.altcha.settings.notice",
                "info"
            ];
        }

        return parent::getFormNotes($section);
    }

}
