# Translate v1.1.0 — cPanel/PWA

Lightweight translation web app for `translate.63.lv`, designed for ordinary reseller cPanel hosting with no database requirement.

## v1.1.0 changes

- Light mode is the first/default theme. Dark mode remains available from the header.
- Compact UI language switcher with flags and small country abbreviations.
- Interface languages: Latvian (LV), English (GB) and Russian (RU), with compact flag switching. Translation source/target lists include Spanish, French, German, Italian, Polish, Ukrainian, Lithuanian, Estonian, Danish, Swedish, Norwegian, Finnish, Icelandic and many more languages.
- Fast output shortcuts: Latvian, British English and Russian.
- Android, iPhone/iPad, Windows, macOS and Linux installation buttons/guidance.
- Improved PWA manifest with 192px and 512px icons.
- Microphone recordings and uploaded audio/video stay in the browser/device and are never written to server storage.
- Translated MP3 output is generated in memory and streamed directly to the browser for device download; no MP3 file is saved on the server.
- Uploaded documents are read locally in the browser. Only translation text chunks are sent to the translation endpoint.
- Small server-side translation text cache reduces repeated external translation calls. Cache files are private and automatically expire after 24 hours.

## Install on cPanel

1. Upload all files from this ZIP into the document root for `translate.63.lv`.
2. Use HTTPS. Microphone, PWA installation and service workers require a secure origin.
3. PHP 8.0+ is recommended. cURL is preferred; `allow_url_fopen` is a fallback.
4. Make sure `storage/cache/` is writable by PHP if you want 24-hour response caching. The app still works without cache writes.
5. Optional provider settings can be copied from `config.local.php.example` to `config.local.php`.

## Device-local media policy

- Local audio/video selected by the user is loaded with `URL.createObjectURL()` and remains on the device.
- Microphone recordings use `MediaRecorder` and remain as browser Blob data until downloaded or the page is closed.
- MP3 translation export is streamed to the browser; it is not saved to `storage/`.
- `storage/cache/` contains only translation text-response cache and is denied from direct web access. Entries older than 24 hours are deleted automatically on API activity.

## PWA installation notes

- Android: Chrome → Install app / Add to Home screen.
- iPhone/iPad: Safari → Share → Add to Home Screen.
- Windows: Edge/Chrome → Install app.
- macOS: Chrome/Edge → Install, or Safari → File → Add to Dock where supported.
- Linux: Chrome/Chromium → Install app.

These are PWA installs, not native App Store / Play Store packages. They use the same lightweight web app and therefore avoid maintaining separate Android, iOS, Windows, macOS and Linux codebases.

## v1.3.4 security, cache and Bluetooth controls

- The whole app and API are protected by a PHP session login.
- Default password: `Translate@63` (change it by setting `login_password_hash` in `config.local.php`).
- Optional hCaptcha can be enabled from **Security & cache** inside the logged-in app. Enter your hCaptcha site key and secret, save, log out, and the next login will require hCaptcha.
- **Clean server cache** removes all translation-text cache immediately. Automatic expiry remains 24 hours.
- Uploaded audio/video and microphone recordings remain on the device. Generated translated MP3 is streamed directly to the browser and not stored as a server media file.
- **Bluetooth / earphones** lets the user detect available microphone and output devices and choose translated audio routing: **Left**, **Right**, or **Both**. Bluetooth pairing itself must be done in the operating system Bluetooth settings; browsers cannot pair arbitrary audio headsets directly.
- Audio output selection requires browser support for `HTMLMediaElement.setSinkId()`; Chromium browsers generally provide the best support. Speech recognition may still use the operating system's active/default microphone because the Web Speech API does not standardize per-recognition input device selection.
- The service worker caches only static assets. Authenticated HTML and API responses are always fetched from the server and are never stored in the PWA cache.

- v1.3.4 adds a local Latvian writing assistant for diacritics, punctuation, spacing, sentence capitalization, red-wave review, and one-click fixes.


### v1.3.4
- Replaced emoji country flags with local SVG country flags.
- Replaced emoji Android, Apple, Windows, macOS and Linux install icons with local SVG platform icons.


### v1.3.4 microphone quality
- PC/mobile microphone chooser, quiet speech/noisy room/headset/raw profiles.
- Input boost, sensitivity threshold, noise suppression, echo cancellation and AGC controls.
- Live microphone level test meter.
- Settings stored locally on the device.


## v1.3.5
- Live speech now shows interim recognition text and translates after a short pause even when the browser does not emit a final result.
- Added live recognition status/error feedback and safer automatic restart.
- Persistent hCaptcha settings are no longer bundled in release ZIPs; existing storage/settings.json remains untouched during normal upgrades.
- Server keeps only minimal translation cache, automatically expiring after 24 hours.


## v1.3.7
- Live speech diagnostic status and speech test.
- Transcript now includes recognized interim/final speech even when translation fails.
- Background music is no longer described as confirmed speech.


## v1.3.7 UX changes
- Settings moved to a dedicated Settings view opened by the gear button.
- Google/Tilde now show an in-app confirmation modal before opening a new tab.
- Audio/video and document uploads have full drag-and-drop areas.
- Select chevrons are vertically centered and language menu horizontal overflow is removed.


## v1.3.9
- Green active navigation tabs to match brand.
- Smart phrase merge for live speech.
- Conversation and TV/background music listening modes.


## v1.4.0
- Dark mode contrast and consistency refresh.


## v1.4.1
- Added YouTube, TikTok, Spotify and generic media URL player support.
- Added browser tab/system audio capture translation mode (best effort, browser dependent).


## v1.4.2
Enhanced free voice selection: quality preference, male/female preference, warm/soft/clear tone, rate and pitch. Latvian prioritizes Nils/Everita and Natural/Online/Neural voices when the browser exposes them.


## v1.4.3
- Persistent tab-audio sharing session: one browser share prompt per session.
- Transcript-first media mode with visible cumulative recognized text and phrase buffering before translation.
- Latvian TTS safety: natural pitch 1.0 and conservative rate to reduce distorted pronunciation.
- Latvian browser voice is preferred over low-quality routed server TTS when normal stereo output is used.


## v1.4.6 — High accuracy media transcription
- Added a media recognition selector: **Fast browser speech recognition** or **High accuracy local Whisper**.
- High accuracy mode runs `Xenova/whisper-base.en` in the user's browser/device and processes shared tab/system audio in ~12 second chunks before translating.
- The Whisper model is downloaded by the browser on first use (roughly 100–200 MB depending on browser/model cache) and is not stored by the cPanel application.
- Shared media audio remains device-side; only the recognized text is sent to the selected translation provider.
- Local audio/video files can also use the high-accuracy transcription path when the browser exposes their audio track.
- Existing fast Web Speech mode remains available for low-latency conversation.
- Existing `storage/settings.json` is still not bundled, so hCaptcha settings are preserved during upgrades.


## v1.4.6 performance fix
- Switched local media transcription from Whisper base.en to quantized tiny.en.
- Reduced segment duration to 8 seconds.
- Added a hard bounded audio buffer and a single latest pending segment.
- Drops/replaces stale waiting segments instead of allowing RAM growth.
- Stops unnecessary screen video capture after tab-audio permission to reduce GPU/CPU usage.
- Yields between inference jobs to keep UI/video responsive.
