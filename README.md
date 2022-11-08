# Inline Games [![License](https://img.shields.io/github/license/jacklul/inlinegamesbot.svg)](https://github.com/jacklul/inlinegamesbot/blob/master/LICENSE) [![Telegram](https://img.shields.io/badge/Telegram-%40inlinegamesbot-blue.svg)](https://telegram.me/inlinegamesbot)

A Telegram bot that provides real-time multiplayer games that can be played in any chat.

You can see the bot in action by messaging [@inlinegamesbot](https://telegram.me/inlinegamesbot).

#### Currently available games:

- Tic-Tac-Toe
- Tic-Tac-Four ([@DO97](https://github.com/DO97))
- Elephant XO ([@DO97](https://github.com/DO97))
- Connect Four
- Rock-Paper-Scissors
- Rock-Paper-Scissors-Lizard-Spock ([@DO97](https://github.com/DO97))
- Russian Roulette
- Checkers
- Pool Checkers

## Deploying

### Google Cloud Platform

- Copy `env_variables.example.yaml` into `env_variables.yaml` and fill out the details
- Run the deployment command: `gcloud app deploy --project YOUR-PROJECT-NAME-HERE app.yaml`
- Visit `https://YOUR-PROJECT-NAME-HERE.appspot.com/admin?a=install` to install database schema (when applicable)
- Visit `https://YOUR-PROJECT-NAME-HERE.appspot.com/admin?a=set` to set the webhook

### Heroku

[![Deploy](https://www.herokucdn.com/deploy/button.svg)](https://heroku.com/deploy?template=https://github.com/jacklul/inlinegamesbot)

Assuming everything was entered correctly the deploy process should run the following commands automatically and your bot should be instantly working:
- `php bin/console install` - install database schema
- `php bin/console set` - set the webhook

If it doesn't you will have to open your app's console and run them manually.

You will also want to add **Heroku Scheduler** addon and set up a hourly task to run the following command to clean up expired games from the database:
- `php bin/console cron`

If this command times out too fast try using something like this instead: `php -d max_execution_time=2700 bin/console cron`

## Note on translations

Translations support is implemented but it is not used mainly because translated text would be displayed to both players - this could be problematic in "gaming" groups - people setting language that other player can't understand!

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) for more information.

## License

See [LICENSE](LICENSE).
