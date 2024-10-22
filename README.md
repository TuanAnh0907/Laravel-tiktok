# TikTok Login Kit for PHP
**TikTok Login Kit** implementation in PHP based on the [official documentation](https://developers.tiktok.com/doc/login-kit-web/).

This is an unofficial SDK for the official Login Kit APIs.

## Features

**The library has been updated to include video/image publish via Direct Post**

Current features include:

- Log in with TikTok
- Retrieve Basic User Information
- Retrieve Advanced User Information
- Retrieve Videos
- Paginate Videos
- Refresh Expired Token
- **NEW** Publish a Video with "Direct Post" via URL
- **NEW** Publish a Video with "Direct Post" via File
- **NEW** Publish one or more Images with "Direct Post" via URLs

## Installation

Install via Composer

```
composer require tuan-anh/laravel-tiktok
```

## Requirements

You need to have your app set up and approved in the [TikTok Developer Portal](https://developers.tiktok.com/).

If you're upgrading to the v2 TikTok API version, make sure you've added your *Redirect URLs* and selected the proper scopes.

## Requirements for Direct Posts

If you're planning to publish videos/photos via Direct Post, you need to undergo an audit. More info [here](https://developers.tiktok.com/application/content-posting-api)

Until you're approved:
- You can only publish private videos
- The account that you use for testing must also be private
