# Migrating from 1.x to 2.x

1. Do a proper backup of the directory you have bound for your runtime cache (`/opt/runtime-cache` in container).
2. Add a new bound volume for the database, it lives at `/opt/database` in the container.
3. Open any page on the scheduler, it should automatically migrate all jobs.
   1. Note that it might take a while if you have many of them.
4. After you test that everything works, you may remove the bind volume for `/opt/runtime-cache`.
