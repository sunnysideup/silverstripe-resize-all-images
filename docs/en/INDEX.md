# How to run it:

This only runs from the command line:

```shell

vendor/bin/sake dev/tasks/resize-all-images
vendor/bin/sake dev/tasks/resize-all-images --for-real=1
```

If you find that your images are not showing in the browser, even though they exist in the database and even though they exist in the filesystem then you should check their hashes. This is how you can fix all the hashes on your site: 
```
vendor/bin/sake dev/tasks/fix-hashes
vendor/bin/sake dev/tasks/fix-hashes --for-real=1
```
