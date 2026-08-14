# Google Drive photos (OAuth + refresh token)

Everything needed for local **and** deploy lives in **`.env`**. No service-account JSON file.

## Quick path

1. Copy `.env.example` → `.env` in the project root (next to `public_html`, not inside it)
2. Create Google OAuth **Web client** + enable Drive API
3. Put `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_DRIVE_FOLDER_ID` in `.env`
4. As Editor open **Drive Setup** → **Connect Google Drive**
5. Paste the shown `GOOGLE_REFRESH_TOKEN` into `.env`
6. Use the same `.env` values on the server

## Detailed steps

### 1. Google Cloud

1. [Google Cloud Console](https://console.cloud.google.com/) → project
2. Enable **Google Drive API**
3. Configure OAuth consent screen (External is fine for testing; add yourself as test user)
4. Credentials → **Create credentials** → **OAuth client ID**
5. Type: **Web application**
6. Authorized redirect URIs — add exactly what Drive Setup shows, e.g.:

```text
http://localhost:8080/api/google_oauth.php
```

For production, also add:

```text
https://your-domain.com/api/google_oauth.php
```

and set in `.env`:

```env
GOOGLE_OAUTH_REDIRECT_URI=https://your-domain.com/api/google_oauth.php
```

### 2. `.env` values

```env
GOOGLE_CLIENT_ID=xxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxx
GOOGLE_DRIVE_FOLDER_ID=1e3tcePyJYdC2fzu_-8AStACoLj4wpPqi
GOOGLE_REFRESH_TOKEN=
GOOGLE_DRIVE_MAKE_PUBLIC=true
```

`GOOGLE_DRIVE_FOLDER_ID` can be the ID only **or** a full folder URL.

Create the folder in **your** Google Drive (the same Google account you will authorize).

### 3. Get refresh token (once)

1. Log into CRM as **Editor**
2. Open **Drive Setup** in the nav
3. Click **Connect Google Drive**
4. Approve access
5. Copy `GOOGLE_REFRESH_TOKEN=...` into `.env`
6. Restart is not required; next upload uses the new token after refresh

If Google does not return a refresh token: open [Google Account permissions](https://myaccount.google.com/permissions), remove the app, connect again.

### 4. Deploy

Copy these from local `.env` to the server `.env`:

- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REFRESH_TOKEN`
- `GOOGLE_DRIVE_FOLDER_ID`
- `GOOGLE_OAUTH_REDIRECT_URI` (production callback)
- `GOOGLE_DRIVE_MAKE_PUBLIC`

Same refresh token works on the server — you do **not** need the JSON key file.

## How uploads work

1. CRM uses refresh token → short-lived access token
2. Photo uploads into your Drive folder
3. Link (+ file id) saved in MySQL
4. Patient / Ameer screens load the image from that link

## Security

| Item | In git? |
|------|---------|
| `.env.example` | Yes |
| `.env` | **No** |
| Client secret / refresh token | **No** (only in `.env`) |
