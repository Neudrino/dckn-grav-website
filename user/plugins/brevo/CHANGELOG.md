# 1.1.0
## 10/25/2026

1. [](#new)
    * Optional double opt-in via Brevo's DOI flow (`contacts/doubleOptinConfirmation`)
    * New `doi.enabled`, `doi.template_id` and `doi.redirection_url` plugin settings
    * Falls back to single opt-in (with a logged error) when DOI is enabled without a template ID
    * Single opt-in behaviour is unchanged when DOI is disabled

# 1.0.0
## 04/27/2026

1. [](#new)
    * Initial release
    * Brevo contacts API integration as a Grav Form process action
    * Supports multiple list IDs per form submission
    * Supports custom contact attribute field mappings
    * Falls back to default list ID from plugin config when none specified in form
    * Errors logged to Grav log for easy debugging
