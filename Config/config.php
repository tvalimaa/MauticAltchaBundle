<?php declare(strict_types=1);

use MauticPlugin\MauticAltchaBundle\EventListener\AltchaFormSubscriber;
use MauticPlugin\MauticAltchaBundle\Service\AltchaClient;
use MauticPlugin\MauticAltchaBundle\Integration\AltchaIntegration;
use MauticPlugin\MauticAltchaBundle\Controller\ChallengeController;

use Mautic\CoreBundle\Helper\AppVersion;

// assume that Mautic developers use sane versioning
$mauticVersion = str_replace(".", "", explode("-", (new AppVersion())->getVersion())[0]);

$mauticVersion = str_split((string)$mauticVersion);

switch(true) {
    case $mauticVersion[0] >= 6:
        $defaultIntegrationArguments = [
            "event_dispatcher",
            "mautic.helper.cache_storage",
            "doctrine.orm.entity_manager",
            "request_stack",
            "router",
            "translator",
            "monolog.logger.mautic",
            "mautic.helper.encryption",
            "mautic.lead.model.lead",
            "mautic.lead.model.company",
            "mautic.helper.paths",
            "mautic.core.model.notification",
            "mautic.lead.model.field",
            "mautic.plugin.model.integration_entity",
            "mautic.lead.model.dnc",
            "mautic.lead.field.fields_with_unique_identifier"
        ];
        break;
    case $mauticVersion[0] >= 5:
        $defaultIntegrationArguments = [
            "event_dispatcher",
            "mautic.helper.cache_storage",
            "doctrine.orm.entity_manager",
            "session",
            "request_stack",
            "router",
            "translator",
            "monolog.logger.mautic",
            "mautic.helper.encryption",
            "mautic.lead.model.lead",
            "mautic.lead.model.company",
            "mautic.helper.paths",
            "mautic.core.model.notification",
            "mautic.lead.model.field",
            "mautic.plugin.model.integration_entity",
            "mautic.lead.model.dnc",
            "mautic.lead.field.fields_with_unique_identifier"
        ];
        break;
    default:
        throw new \RuntimeException("MauticAltchaBundle is not compatible with your Mautic version. Please remove the plugin.");
}

return [
    "name"        => "ALTCHA",
    "description" => "Adds a self-hosted, privacy-friendly ALTCHA (proof-of-work) CAPTCHA field to Mautic forms.",
    "version"     => "1.0.0",
    "author"      => "Your Name / Company",

    "routes" => [
        "public" => [
            "mautic_altcha_challenge" => [
                "path"       => "/altcha/challenge",
                "controller" => ChallengeController::class // invokable - see ChallengeController::__invoke()
            ]
        ]
    ],

    "services" => [
        "events" => [
            "mautic.altcha.event_listener.form_subscriber" => [
                "class" => AltchaFormSubscriber::class,

                "arguments" => [
                    "event_dispatcher",
                    "mautic.altcha.service.altcha_client",
                    "mautic.lead.model.lead",
                    "request_stack",
                    "mautic.helper.integration"
                ]
            ]
        ],

        "models" => [

        ],

        "others" => [
            "mautic.altcha.service.altcha_client" => [
                "class" => AltchaClient::class,

                "arguments" => [
                    "mautic.helper.integration",
                    "router"
                ]
            ]
        ],

        "integrations" => [
            "mautic.integration.altcha" => [
                "class"     => AltchaIntegration::class,
                "arguments" => $defaultIntegrationArguments
            ]
        ]
    ],

    "parameters" => [

    ]
];
