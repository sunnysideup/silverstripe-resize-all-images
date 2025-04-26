# How to run it

Firstly, review [Resizing Module](https://github.com/sunnysideup/silverstripe-scaled-uploads/) and set up the right configuration.

Then run the command listed below.  Firstly do a dry run and review the results. This only runs from the command line:

```shell
vendor/bin/sake dev/tasks/resize-all-images
vendor/bin/sake dev/tasks/resize-all-images --for-real=1
```

If you find that your images are not showing in the browser, even though they exist in the database and even though they exist in the filesystem then you should check their hashes. This is how you can fix all the hashes on your site:

```shell
vendor/bin/sake dev/tasks/fix-hashes
vendor/bin/sake dev/tasks/fix-hashes --for-real=1
```
