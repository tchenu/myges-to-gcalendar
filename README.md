# MyGes to Google Calendar
Script to link MyGes calendar and Google Calendar.  📅

# Warning

This project is not maintained, it has been designed for personal use only.

# Usage 

1. Install dependencies with ``composer install``
2. Rename ``.env.example`` file to ``.env`` and set up it with your MyGes credentials.
3. You must enable the Google Calendar API : ``https://developers.google.com/calendar/quickstart/php``
4. Download the ``credentials.json`` file and put it in the project folder.
5. Finally run ``php sync.php``

# Cron

I recommand to run the ``php sync.php`` once time every days with a cron task because the script import the current month.

``0 5 * * * /usr/bin/php <path-to-the-project>/sync.php > /dev/null 2>&1``
