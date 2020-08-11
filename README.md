# mparticle_submission
mParticle submission handler for webform and custom module

# Global configuration
Global settings for custom module, this settings will require event name. You can add settings at this page or add in custom module settings. Settings page is at: /admin/config/mparticle_submission/mparticlesubmissionconfig

# Webform submission handler settings
At this section you need to configure following fields.
* Mparticle API Endpoint: mparticle API endpoint, [detail](https://docs.mparticle.com/developers/data-localization/#events-api)
* Mparticle API Key: mparticle API key
* Mparticle API Secret: mparticle APY secret
* Environment: mparticle API environment
* Event name: see event type, [detail](https://docs.mparticle.com/developers/server/http/#v2events)

# Webform custom data mapping
Custom data is mapped in YML format.
[Webform custom data example](https://github.com/bullseye-digital/mparticle_submission/raw/master/custom_data.jpg)

# Custom module usage
* Call service statically or use Dependency injection


