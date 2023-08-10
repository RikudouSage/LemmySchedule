# Lemmy Schedule

An app that makes it possible to schedule posts on Lemmy.

> Am I the only one who thinks it's funny how the name "Lemmy Schedule" sounds like "let me schedule"?

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
