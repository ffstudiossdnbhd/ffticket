# FFTicket

FFTicket is a PHP/MySQL REST API with matching web and Windows desktop clients for IT support ticketing.

## Structure

- `backend/` - PHP 8 API using PDO, JWT, dotenv, and Telegram notifications.
- `backend/database/schema.sql` - MySQL 8 schema and starter categories.
- `web/` - responsive PHP web client.
- `desktop/FFTicket.Desktop/` - .NET 8 WinUI desktop app.

## Docker Web Setup

1. Ensure Docker Desktop is running.
2. Run `powershell -ExecutionPolicy Bypass -File docker/setup.ps1`.
3. Open FFTicket at `http://localhost:8110`.
4. Open phpMyAdmin at `http://localhost:8111`.

The setup script generates private database, JWT, and first-run administrator credentials in the ignored root `.env` file. Change the administrator password after the first sign-in. Existing Docker volumes are preserved on subsequent starts.

## Backend Setup

1. Enable PHP extensions required by Composer and runtime: `openssl`, `pdo_mysql`, `fileinfo`, `mbstring`, and `curl`.
2. From `backend/`, run `composer install`.
3. Copy `backend/.env.example` to `backend/.env` and fill database, JWT, upload, and Telegram values. For Telegram forum topics, set `TELEGRAM_MESSAGE_THREAD_ID` to the target topic ID.
4. Import `backend/database/schema.sql` into the Hostinger MySQL database.
5. Create the first admin user manually with a secure `password_hash()` value, then use the desktop admin screen for future users.
6. Deploy the backend so `/api/...` maps to `backend/api/...`.
7. Keep `backend/.env` and `backend/storage/uploads` out of public listing and source control.

## Desktop Setup

1. Copy `desktop/FFTicket.Desktop/.env.example` to `desktop/FFTicket.Desktop/.env`.
2. Set `API_BASE_URL` to the deployed API URL, for example `https://example.com/api`.
3. Run `dotnet restore desktop/FFTicket.Desktop/FFTicket.Desktop.csproj`.
4. Run `dotnet build desktop/FFTicket.Desktop/FFTicket.Desktop.csproj`.

## Security Notes

- The WPF app never connects to MySQL directly; all data access goes through the PHP API.
- Every API endpoint except login requires a JWT bearer token.
- SQL calls use prepared statements.
- Uploads are limited to PNG, JPG, JPEG, and PDF files up to 10 MB with randomized stored filenames.
- Telegram bot credentials, database credentials, and JWT secrets must stay in `.env` only.
