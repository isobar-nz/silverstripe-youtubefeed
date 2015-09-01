# SilverStripe YouTube Feed

Requests videos from an authenticated YouTube 'My Uploads' feed and converts them into YouTubeVideo DataObjects.

## Features

- Configurable auto-update interval (disabled by default)
- CLI task YouTubeFeedTask `framework/sake YouTubeFeedTask flush=all`
- Stores video information into YouTubeVideo DataObject.

## Installation

Installation via composer

```bash
$ composer require littlegiant/silverstripe-youtubefeed
```

You're then required to create an application at [Google Developers Console](https://console.developers.google.com/).

1. Click 'Create Project'
2. Under "APIs & auth" > "APIs" enable "YouTube Data API"
3. Under "APIs & auth" > "Credentials" click "Add credentials" (OAuth2 Client ID) then 'Configure consent screen' and enter your project name
4. The 'Application Type' should be 'Web application' then for 'Authorized redirect URIs' enter `http://yoursitename.tld/youtube/authenticate` then "Create"
5. Enter your `Client ID` and `Client Secret` into Settings in the CMS

### Options

Auto-update is disabled by default, and can be enabled within the CMS Settings "YouTube" tab.

### Extending YouTube Feed

- `onAfterCreate(YouTubeVideo $videoObject)`

Called after a YouTubeVideo DataObject is created as a result of being found in a playlist of the connected YouTube account.

```php
public function onAfterCreate(YouTubeVideo $videoObject)
{
    // Do something with the newly created $videoObject
}
```

### Contributing

Submit a pull request or issue and i'll try reply on the same working day.

### Code guidelines

This project follows the standards defined in:

* [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md)
* [PSR-1](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md)
* [PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)