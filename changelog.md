#todo

#dev-master

- added a request generator instead of a simple array for Guzzle requests so that further requests can be appended while running. Needed because `$event->retry()` could not be used due to URL limitations during redirects