# Lemmy Schedule

An app that makes it possible to schedule posts on Lemmy.

> Am I the only one who thinks it's funny how the name "Lemmy Schedule" sounds like "let me schedule"?

<!-- TOC -->
* [Lemmy Schedule](#lemmy-schedule)
  * [Interesting parts](#interesting-parts)
  * [How it works?](#how-it-works)
  * [Building the app locally](#building-the-app-locally)
    * [Prerequisites](#prerequisites)
    * [Configuring](#configuring)
      * [Job transports](#job-transports)
      * [File uploading](#file-uploading)
      * [Cache](#cache)
    * [Building](#building)
    * [Running](#running)
  * [Self-hosting - AWS](#self-hosting---aws)
    * [Prerequisites:](#prerequisites-1)
    * [Environment variables](#environment-variables)
    * [Deploying](#deploying)
  * [Self-hosting - docker](#self-hosting---docker)
    * [Configuration](#configuration)
    * [Volumes](#volumes)
    * [Ports](#ports)
    * [Running](#running-1)
      * [Docker compose](#docker-compose)
<!-- TOC -->

## Interesting parts

- [AppAuthenticator](src/Authentication/AppAuthenticator.php) - the authentication process, here you can make sure
  that I'm not a villain trying to steal your precious credentials and then use the power of impersonating you
  to take over the world.
- [CreatePostJob](src/Job/CreatePostJob.php) - the job that gets saved to schedule your post, as you can see
  I store your JWT to impersonate you, but sadly it's impossible to do without.
- [EventBridgeTransport](src/JobTransport/EventBridgeTransport.php) - here you can see that the job is deleted
  immediately after the job is triggered (see the line that reads `'ActionAfterCompletion' => ActionAfterCompletion::DELETE`)

## How it works?

It uses the excellent [Symfony](https://symfony.com) framework and even more excellent 
[Symfony Messenger](https://symfony.com/doc/current/messenger.html).

Basically, it creates a Messenger job and configures a delay for the job that is the same as the time of the post.
The job is then received, processed and deleted (depends on your particular messenger backend).

The default deployment using the provided `serverless` configuration has a little twist on that: to be fully serverless,
the messenger backend is faked to be an EventBridge service and it only allows creating jobs, which it does by creating
a scheduled job which in turn posts to a console command [`app:run-sync`](src/Command/RunJobSynchronously.php).

The console command takes the job, overwrites the transport config to force it to run synchronously in the command
and thus the need for a queue consumer is avoided.
I'm still not sure whether this is a genius idea or a blasphemy.
But it saves me money for hosting, so I'm gonna go with genius.

Instead of database it uses a cache backend which can be any PSR-6 backend, the default configuration uses
a DynamoDB one.

## Building the app locally

> Note that this is a tutorial for a fully local deployment, depending on the method you'll be using
> for deployment (Docker, AWS serverless), you might not need to set up everything mentioned below, feel free
> to skip to the specific guides below and use this only as a reference if you want a deeper understanding of
> how the app works.

Here's a brief tutorial on how to build a production version of the app.

### Prerequisites

- php (8.2, intl and json extensions), composer etc.
- serverless framework
- yarn (if you use a different node package manager, you can adapt the commands to it)

### Configuring

All configuration is made using environment variables, those can either be real environment variables or ones set
in `.env.local` file.

> If an option is marked as required, but also has a default value, you don't have to provide your own value if you're ok with the default

| **Environment variable**    | **Description**                                                                                                                                                                              | **Default**                                          | **Required** |
|-----------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------|--------------|
| `APP_ENV`                   | can be either `prod` or `dev`                                                                                                                                                                | `dev`                                                | **yes**      |
| `APP_SECRET`                | a random string used for signing cookies etc., should be unique and, well, as the name implies, secret                                                                                       | **do not use the default value, always provide one** | **yes**      |
| `DYNAMODB_CACHE_TABLE`      | the name of the AWS DynamoDB table to use for cache, ignore this option if you don't use DynamoDB for cache                                                                                  | `cache`                                              | no           |
| `AWS_REGION`                | the name of the AWS region to use, you can ignore this if you're not using DynamoDB for cache and AWS EventBridge as a job scheduler                                                         | `eu-central-1`                                       | no           |
| `MESSENGER_TRANSPORT_DSN`   | the DSN for job transport, more below                                                                                                                                                        | `redis://localhost:6379/messages`                    | **yes**      |
| `DOMAIN_NAME`               | the domain this will be running on, only needed when running on AWS serverless                                                                                                               |                                                      | no           |
| `CONSOLE_FUNCTION`          | the AWS ARN of the console function, only needed when running on AWS serverless                                                                                                              |                                                      | no           |
| `ROLE_ARN`                  | the AWS ARN of the role used for running jobs, only needed when running on AWS serverless                                                                                                    |                                                      | no           |
| `DEFAULT_INSTANCE`          | the default instance for logging in                                                                                                                                                          | `lemmings.wolrd`                                     | **yes**      |
| `FILE_UPLOADER_CLASS`       | the class that handles uploading of files, note that **this needs to be set prior to compiling the service container**                                                                       | `App\FileUploader\LocalFileUploader`                 | **yes**      |
| `LOCAL_FILE_UPLOADER_PATH`  | the filesystem path to store files in temporarily - the default uses a volatile location that might get deleted often, so it's advised to change it                                          | `%kernel.project_dir%/var/images`                    | no           |
| `S3_FILE_UPLOADER_BUCKET`   | the bucket to use for storing files temporarily, only used if file uploader is set to AWS S3                                                                                                 |                                                      | no           |
| `APP_CACHE_DIR`             | the directory for storing cache and database, it should be some permanent directory                                                                                                          |                                                      | no           |
| `APP_LOG_DIR`               | the directory for storing logs                                                                                                                                                               |                                                      | no           |
| `SINGLE_INSTANCE_MODE`      | set to either 1 or 0, 1 means that only users from the instance specified in `DEFAULT_INSTANCE` can log in                                                                                   | 0                                                    | **yes**      |
| `IMGUR_ACCESS_TOKEN`        | set to Imgur access token if you want to enable Imgur                                                                                                                                        |                                                      | no           |
| `UNREAD_POSTS_BOT_JWT`      | the JWT token for the bot user that will send reports with unread posts - if it's not provided, the whole unread posts functionality will be disabled                                        |                                                      |              |
| `UNREAD_POSTS_BOT_INSTANCE` | the instance the bot for unread post reports is on - if it's not provided, the whole unread posts functionality will be disabled                                                             |                                                      |              |
| `DEFAULT_POST_LANGUAGE`     | the numerical ID of the language that will be preselected when scheduling a post. For list of IDs see [this enum](https://github.com/RikudouSage/LemmyApi/blob/master/src/Enum/Language.php) | 0                                                    | **yes**      |
| `FLAG_COMMUNITY_GROUPS`     | whether community groups, an experimental feature, should be enabled                                                                                                                         | 0                                                    | no           |
| `SOURCE_URL`                | the URL of the source code, used for new version checks and for a footer link - if you plan on maintaining a fork, you can change it to your fork URL                                        | https://github.com/RikudouSage/LemmySchedule         | **yes**      |
| `NEW_VERSION_CHECK`         | set to 1 or 0 to enable or disable checks for a new version                                                                                                                                  | 1                                                    | **yes**      |
| `DEFAULT_COMMUNITIES`       | a comma separated list of communities that will be preselected when going to the schedule page                                                                                               |                                                      | no           |

#### Job transports

Job transport is what manages where the scheduled jobs are stored
and where does the messenger component consume them from.
You can see the full list of officially supported transports 
in the [official Symfony documentation](https://symfony.com/doc/current/messenger.html#messenger-transports-config).

If you're not familiar with the Messenger component, I advise you to go with Redis which is the simplest to configure.
The Redis DSN looks like this: `redis://localhost:6379/messages`.

This configures the server (`localhost`), port (`6379`) and key (`messages`) where the jobs will be stored.
For more complex examples visit the [official documentation](https://symfony.com/doc/current/messenger.html#redis-transport).

#### File uploading

Currently, there are two options for storing uploaded files — locally or in AWS S3. In either case, the files
are deleted once the job that references them is triggered.

These correspond to these `FILE_UPLOADER_CLASS` values:

> Note that this environment variable needs to be set prior to compiling the service container 

- local - `App\FileUploader\LocalFileUploader`
- S3 - `App\FileUploader\S3FileUploader`

For local, you need to also specify `LOCAL_FILE_UPLOADER_PATH` which is a directory in which the file uploads
will be stored.
You can use a special variable `%kernel.project_dir%` that gets replaced with the path to where the project is stored.

If you go with S3, you also need to specify `S3_FILE_UPLOADER_BUCKET` which is the name of the bucket where
the files will be stored.


#### File providers

File providers are handlers for uploading images. Some of them might need configuration before they show up in the UI.

Currently supported ones:

- **instance upload** - the default, always supported
- **Imgur** - an Imgur access token is required

#### Cache

If you're **not** using DynamoDB for cache, 
delete the file [config/packages/prod/rikudou_dynamo_db_cache.yaml](config/packages/prod/rikudou_dynamo_db_cache.yaml)
(before compiling the service container).

### Building

- **Install the production php dependencies** - `composer install --no-dev --no-scripts`
- **Install node dev dependencies** - `yarn install`
- **Build static JS assets** - `yarn build`
- **Compile the production service container** - `./bin/console cache:warmup --env=prod`

Done!

### Running

If you want to test it out, run the development php server using `php -S 127.0.0.1:8000`, otherwise follow
some guide to set up a webserver, Apache being the easiest solution.

## Self-hosting - AWS

> If you intend to self-host using docker, skip this section

> Note: This flow can also always be checked in [publish.yaml](.github/workflows/publish.yaml)

### Prerequisites:

- read the [section on building the app](#building-the-app-locally)
- aws cli
- a domain in AWS Route53

### Environment variables

These variables are provided automatically and you cannot change them:

- `APP_ENV`
- `APP_SECRET`
- `DYNAMODB_CACHE_TABLE`
- `MESSENGER_TRANSPORT_DSN`
- `S3_FILE_UPLOADER_BUCKET`
- `FILE_UPLOADER_CLASS`
- `ROLE_ARN`
- `APP_CACHE_DIR`
- `APP_LOG_DIR`

You need to set these environment variables (as real environment variables, not as part of .env.local):

- `DOMAIN_NAME`
- `DOMAIN_ZONE` - the zone ID of the Route53 domain

You may set these environment variables as well (as real environment variables, not as part of .env.local):

- `SINGLE_INSTANCE_MODE`
- `IMGUR_ACCESS_TOKEN`
- `UNREAD_POSTS_BOT_JWT`
- `UNREAD_POSTS_BOT_INSTANCE`
- `FLAG_COMMUNITY_GROUPS`

### Deploying

- Follow the guide on [building](#building)
- Deploy using the `serverless` command: `serverless deploy --stage prod --verbose`
  - The `--stage prod` can be changed to `--stage dev` for a dev build
  - The `--verbose` flag can be skipped if you want less output

If everything works out,
you should be able to visit the domain which you specified in `DOMAIN_NAME` and the app should be running there!

## Self-hosting - docker

### Configuration

You need to configure these variables:

- `APP_SECRET` - a random string around 32 characters length
  - you can use, for example, this command to generate one: `hexdump -vn16 -e'4/4 "%08x" 1 "\n"' /dev/urandom`

Other variables which might need changing:

- `DEFAULT_INSTANCE` - set this to your preferred instance, otherwise the default `lemmings.world` will be used
- `MESSENGER_TRANSPORT_DSN` - the transport name is by default set to expect a container with hostname `redis`,
  you might need to change this if your setup is different
  - Read the [documentation on the transport](#job-transports) in this README
- `APP_ENV` - you might want to change this to `dev` if you're debugging the app, otherwise leave it out (or set to `prod`)
- `FILE_UPLOADER_CLASS` - you may want to change how uploaded files are handled
  - Read the [documentation on uploading files](#file-uploading) in this README
- `SINGLE_INSTANCE_MODE` - set to 1 if you want to run in single instance mode where only people from instance set in
  `DEFAULT_INSTANCE` can log in
- `IMGUR_ACCESS_TOKEN` - the access token to use with Imgur, leave empty to not support Imgur
- `UNREAD_POSTS_BOT_JWT` - JWT token of the bot user that will send unread post reports
- `UNREAD_POSTS_BOT_INSTANCE` - the instance of the aforementioned bot user
- `FLAG_COMMUNITY_GROUPS` - whether to enable community groups, an experimental feature

### Volumes

Some permanent files are accessible at these locations:

- `/opt/runtime-cache` - used for storing configuration etc., **MUST be bound to a volume**
- `/opt/uploaded-files` - directory for uploaded images, **MUST be bound to a volume**

### Ports

The app runs on port 80 inside the container, bind the port to any port you want.

### Running

Taking the above into account, here is a docker command used for the production build (replace the `APP_SECRET`):

- `docker run -d -it -p 8000:80 -v files:/opt/uploaded-files -v cache:/opt/runtime-cache -e DEFAULT_INSTANCE=my.instance.tld -e APP_SECRET=5d640026bb762a0e874c1e1f2656e079 ghcr.io/rikudousage/lemmy-schedule:latest`

If you have a Redis instance under different URL
(replace `redis.example.com` with your hostname and `lemmy_schedule` with the Redis key you want to use):

- `docker run -d -it -p 8000:80 -v files:/opt/uploaded-files -v cache:/opt/runtime-cache -e DEFAULT_INSTANCE=my.instance.tld -e APP_SECRET=5d640026bb762a0e874c1e1f2656e079 -e MESSENGER_TRANSPORT_DSN=redis://redis.example.com/lemmy_schedule ghcr.io/rikudousage/lemmy-schedule:latest`

#### Docker compose

You can easily run the app using docker compose like so:

```yaml
version: "3.7"

services:
  redis:
    image: redis
    hostname: redis
    command: redis-server --save 60 1 --loglevel warning # make Redis dump the contents to disk and restore them on start
    volumes:
      - redis_data:/data
  lemmy_schedule:
    image: ghcr.io/rikudousage/lemmy-schedule:latest
    ports:
      - "8000:80" # replace 8000 with the port you want your app to run on
    environment:
      APP_SECRET: $APP_SECRET # actually create the secret, don't just use this value
      DEFAULT_INSTANCE: my.instance.tld
    volumes:
      - ./volumes/lemmy-schedule-cache:/opt/runtime-cache
      - ./volumes/lemmy-schedule-uploads:/opt/uploaded-files
    depends_on:
      - redis

volumes:
  redis_data:
```
