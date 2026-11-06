# Release Notes for hostily Captcha

## 0.2.0 - 2026-11-04

### Added
- Two new modes, "Press and hold" and "Slider". Both always offer the alternative "Confirm with a click" for visitors who cannot hold or drag.
- Mode "As in the customer portal": the plugin sets no mode and the widget uses the setting of the project.
- Optional hold time (800-3000 ms) for "Press and hold"; also as option `holdTime` of `{{ hostilyCaptcha() }}`.
- Works in the Twig function, Formie and Freeform alike.

### Changed
- An already saved mode is kept; new installations start with "Visible".

## 0.1.2 - 2026-10-31

### Changed
- License file renamed to `LICENSE.txt` as required by the Craft Plugin Store.

## 0.1.1 - 2026-10-31

### Added
- Plugin icon and changelog for the Craft Plugin Store.

## 0.1.0 - 2026-10-28

### Added
- Initial release: `{{ hostilyCaptcha() }}` Twig function and server-side verification for your own forms.
- Captcha integration for Formie and Freeform.
- Protection of the front-end account forms (registration, password reset).
- Settings with environment variables (`$HOSTILY_CAPTCHA_SECRET`), visible/invisible mode, widget language, behaviour on service failure.
