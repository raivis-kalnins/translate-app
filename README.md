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


## v1.4.7 — High accuracy media transcription
- Added a media recognition selector: **Fast browser speech recognition** or **High accuracy local Whisper**.
- High accuracy mode runs `Xenova/whisper-base.en` in the user's browser/device and processes shared tab/system audio in ~12 second chunks before translating.
- The Whisper model is downloaded by the browser on first use (roughly 100–200 MB depending on browser/model cache) and is not stored by the cPanel application.
- Shared media audio remains device-side; only the recognized text is sent to the selected translation provider.
- Local audio/video files can also use the high-accuracy transcription path when the browser exposes their audio track.
- Existing fast Web Speech mode remains available for low-latency conversation.
- Existing `storage/settings.json` is still not bundled, so hCaptcha settings are preserved during upgrades.


## v1.4.7 performance fix
- Switched local media transcription from Whisper base.en to quantized tiny.en.
- Reduced segment duration to 8 seconds.
- Added a hard bounded audio buffer and a single latest pending segment.
- Drops/replaces stale waiting segments instead of allowing RAM growth.
- Stops unnecessary screen video capture after tab-audio permission to reduce GPU/CPU usage.
- Yields between inference jobs to keep UI/video responsive.


## v1.4.7 tab-audio reliability
- Whisper inference moved to a Web Worker to keep the page responsive.
- Keeps Chrome display capture alive and transcribes an audio-only clone.
- Adds real tab-audio signal detection before transcription.
- Uses jsDelivr +esm for browser-safe Transformers loading.


## v1.4.8
- Latvian default voice preference changed to Female.
- Automatic Latvian voice ranking strongly prefers Microsoft Everita / Online / Neural when exposed by Edge.
- One-time migration clears an older saved Latvian voice choice so the new female default takes effect after update.

## v1.5.0 performance update
- YouTube captions/subtitles are now the first-choice transcription source when accessible.
- Whisper no longer auto-loads for YouTube when captions are available.
- Captions are cached server-side for up to 24 hours only, using the existing protected cache folder.
- Whisper remains a manual fallback when captions are unavailable.
- Service worker no longer intercepts authenticated navigation/API requests, avoiding the previous Failed to fetch loop.


## v1.5.0 YouTube caption quality fixes
- Adds YouTube subtitle/caption track selector when tracks are exposed by YouTube.
- Filters non-speech cues such as [Music], [applause], [laughter] before accepting captions.
- Rejects caption tracks that contain too little real spoken text instead of pretending transcription succeeded.
- Clear recognition quality choices: YouTube captions, Whisper Tiny audio transcription, microphone fallback.
- Existing storage/settings.json remains excluded from update ZIPs so hCaptcha settings are preserved.


## v1.5.4
- Split Live microphone and Audio / video into separate top navigation modes.
- Live mode uses microphone only.
- Media mode uses YouTube captions/tab audio/local files and stops Live microphone automatically.
- Added clear workflow labels and explanations.


## v1.5.4
- Full-width Live microphone and Audio/video workflows.
- Explicit From language -> To language selectors.
- Dedicated YouTube subtitle load/translate button.
- More robust YouTube caption track discovery and XML fallback.


## v1.5.4
- Long text translation is automatically chunked below free-provider limits.
- Listen now prefers the exact same MP3/TTS path as MP3 export.
- Quiet/TV microphone profile is less aggressive with noise suppression and echo cancellation.


## v1.5.5
- More robust free translation fallback: multiple Google web endpoints + mobile Google fallback.
- 350-character safe chunks with retry/backoff for shared hosting.
- Document translation uses smaller batches and cleaner provider outage handling.


## v1.5.6
- Default server text/caption cache increased to 7 days (configurable 1/3/7/14/30 days).
- Optional Gemini Free AI fallback using a user-supplied Google AI Studio Free Tier key.
- Higher-quality Gemini 3.8 Flash or lighter Gemini 3.5 Flash-Lite can be selected.
- Audio/video files remain device-side; only text/caption/provider results are cached.


## v1.5.7 reliability mode
- Simple text translation is intentionally capped at 500 characters.
- Documents, Live microphone transcripts and Media transcripts are split into <=430-byte chunks and translated sequentially.
- If cPanel outbound translation calls fail, the browser falls back directly to free Google (when CORS permits) and then MyMemory.
- MyMemory browser fallback avoids making the whole private app dependent on reseller-host outbound HTTP access.
- Server provider failure enters a 5-minute cooldown so documents do not wait on the same dead endpoint for every chunk.
- Audio/video files remain device-side; only recognized text is translated.


## v1.5.9 audio controls and media defaults
- Added shared TTS Play/Pause/Resume/Stop controls for text, document and live voice preview playback.
- Long document listening now continues through all TTS chunks instead of forcing a page refresh to stop it.
- YouTube loads with Browser-tab audio + Whisper Tiny as the default media path, with caption track left on Auto as an optional fast path.
- Media mode continues to keep Live microphone capture mutually exclusive.
- Existing hCaptcha/settings.json is not shipped in the update ZIP.


## v1.6.0 media reliability
- Chrome/Edge tab audio remains the default for YouTube/Spotify/podcasts.
- If Free AI Assist is configured, short audio segments can be transcribed with Gemini audio first, with local Whisper Tiny fallback. Audio is forwarded in memory and is not saved by the app server.
- Longer overlapping media windows and word-overlap merging improve continuous speech/song phrasing.
- Local Whisper remains available when AI is unavailable or quota-limited.


## v1.6.1
- YouTube defaults are pinned to browser-tab audio + Whisper Tiny + caption Auto. Captions no longer switch recognition mode.
- Longer 14s music-aware windows with 4s overlap and a vocal-focus WebAudio filter/compressor chain.
- Gemini audio failure is cooled down for 5 minutes per session so it cannot slow every segment.
- Larger safe transcription queue to reduce dropped song fragments.


## v1.6.2
- Faster 8s/2s-overlap media transcription windows.
- Whisper-first transcription; Gemini audio only rescues weak fragments.
- Larger protected media queue.
- Media transcript TXT, bilingual translation TXT, source audio recording download, and translated MP3 download.

## v1.6.3
- Faster live media transcription: 5 second windows with 1.25 second overlap.
- Visible live translated-text panel next to the transcript workflow.
- Every recognized media chunk is translated through a serialized queue without blocking audio capture.
- Optional automatic final accuracy pass after Stop: the device-side recording is decoded locally, re-transcribed in longer windows, compared with the live transcript, and used to fill missed speech.
- Final pass then creates one complete translation for download/listening.
- Manual "Complete from recording" button and preference saved locally.
