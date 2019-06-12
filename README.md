# Sprout Errors
Error handler &amp; logger for WordPress but with an actually smart way of doing things.

Think about it this way: do you want to hit the database every single time you need to log an error? No. Save it to memory (be able to retrieve it as well) as error-codes that you can then decode on the front-end, then, at the end of the request, save it.
