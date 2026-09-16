# Meeting transcription — Google Cloud setup

This directory holds the **service-account** JSON key used for the Meeting
feature's transcription pipeline (Google Speech-to-Text + Cloud Storage).
This is a *different* credential from the Calendar/Drive integration
(`Settings → Calendar`), which uses per-org OAuth instead — this feature
needs a service account because Speech-to-Text/GCS are billed to a GCP
*project*, not to a specific person's Google account.

Files placed here (besides this README) are gitignored — never commit the
JSON key.

## One-time setup (per environment: local, production, etc.)

1. In [Google Cloud Console](https://console.cloud.google.com), open the same
   project already used for Calendar/Drive (or a new one).
2. Enable two APIs: **Cloud Speech-to-Text API** and **Cloud Storage API**.
3. **Enable billing** on the project (required even to stay within the free
   tiers — Speech-to-Text's free tier is 60 minutes/month, perpetual).
4. Create a **Cloud Storage bucket** (e.g. `letzmanage-meeting-audio`) — this
   is only ever used as transient staging for Speech-to-Text; nothing is kept
   there long-term (see `GoogleSpeechToTextService::deleteObject()`).
5. Create a **Service Account** (IAM & Admin → Service Accounts) with two
   roles: `Storage Object Admin` (scoped to the bucket above, or project-wide)
   and `Cloud Speech Client`.
6. Create a JSON key for that service account and download it.
7. Place the downloaded file at `storage/app/google/speech-service-account.json`
   (or anywhere else — just set `GOOGLE_SPEECH_CREDENTIALS_PATH` in `.env` to
   match).
8. In `.env`, set:
   - `GOOGLE_SPEECH_CREDENTIALS_PATH` (only if not using the default path above)
   - `GCS_BUCKET` — the bucket name from step 4
   - `GOOGLE_SPEECH_LANGUAGE_CODE` (default `en-US`, change if meetings aren't in English)

## Gemini (summarization) — separate, simpler credential

No service account or billing project needed for this one:

1. Go to [Google AI Studio](https://aistudio.google.com), get a free API key.
2. Set `GEMINI_API_KEY` in `.env`. `GEMINI_MODEL` defaults to `gemini-1.5-flash`.

## Verifying setup works

Before building/testing anything UI-related, verify the credentials actually
work from `tinker`:

```php
app(\App\Services\GoogleServiceAccountAuthService::class)->getAccessToken();
// should return a real access token string, not throw
```
