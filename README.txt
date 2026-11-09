K.O.D AI BARISTA JSON RESPONSE FIX

FILES IN THIS PATCH
- api/barista-chat.php
- assets/js/barista.js

INSTALL
1. Make a backup of C:\xampp\htdocs\KOD_Cafe.
2. Extract this ZIP.
3. Copy the api and assets folders into C:\xampp\htdocs\KOD_Cafe, merging folders and replacing only the two listed files.
4. Keep your existing .env file unchanged. Do not share your API key.
5. Restart Apache in XAMPP.
6. Open http://localhost/KOD_Cafe/ai-barista.php, press Ctrl+F5, and send one test message.

WHAT THE PATCH CHANGES
- Prevents PHP notices/warnings from being printed as HTML into the API response.
- Returns a safe JSON error if the endpoint throws an exception or encounters a fatal runtime error.
- Logs the underlying PHP error to the Apache/PHP error log.
- Makes the browser report a useful message instead of the generic Unexpected token '<' JSON parse error.

IF IT STILL FAILS
Open C:\xampp\apache\logs\error.log immediately after reproducing the issue. Find the newest line beginning with KOD Barista. Share only the error message and file/line number. Do not share .env contents, API keys, passwords, or full request headers.

Also verify in your browser Developer Tools (F12) > Network > barista-chat.php that the request URL is http://localhost/KOD_Cafe/api/barista-chat.php and that the response Content-Type is application/json.
