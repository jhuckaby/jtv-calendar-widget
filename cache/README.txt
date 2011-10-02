// Justin.TV Calendar Widget "cache" folder README

The "cache" folder is used to temporarily store data from the Google Calendar and JTV APIs on your own server, which is then served up to your users.  We don't want to hit the APIs for every single user request, because (1) they tend to be slow, and (2) we may hit some API limits because from JTV's and Google's perspective all the requests are coming from the same IP address.  This allows the info widget to make one single request to the APIs per 60 seconds, no matter how many live viewers you have.

IMPORTANT NOTE: You must set the permissions on this folder so the web server can read and write to it.  This is typically not the default case if you just upload the files and go.  If you don't know what user your webserver runs as, just set the permissions to 777 (everyone read+write).

	chmod 777 ~/html/jtv-calendar-widget/cache

Or use your FTP app to set "everyone can read, everyone can write" privileges.  Do this on the "cache" folder itself, not this readme file.

