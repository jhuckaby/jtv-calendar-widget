# Overview

The JTV Calendar Widget is a web based component that displays your current live show title based on your Google Calendar.  Or, if your Justin.TV stream is not currently live, it scans the calendar for the next upcoming show (up to 4 weeks in the future), and displays that instead.  This is a simple way to show your viewers what show is live, or what show is coming up next, without them having to read your whole calendar.

## Requirements

You'll need a web server to host the widget, which consists of some HTML, JavaScript and PHP files.  Your server will need PHP version 5 or higher, and you'll need some way to upload and manipulate files, such as an FTP program.  Shared hosting works fine, as long as they allow you to upload and run your own PHP scripts.

And of course, you'll need your own Justin.TV channel, and your own public Google Calendar.  If you don't know what these are, then this widget probably isn't for you :)

# Installation

Note: If you download the widget from GitHub, once you extract the archive you're going to get a folder named something crazy like "`jtv-calendar-widget-a541ed5`".  Please rename this folder so it is just plain old "`jtv-calendar-widget`".

To install the widget, first upload the entire "`jtv-calendar-widget`" folder to your web server via FTP, and make sure it is your public html area.  If you don't know where this is, consult your web hosting company, and ask where to place files to make them web accessible.  For example, when you connect to your website via FTP, you should see some sort of public html folder, which may be called "`public_html`", "`html`" or "`www`".  This is typically where you place files to make them load in a web browser.  Upload the "`jtv-calendar-widget`" so it goes *inside* your "`public_html`", "`html`" or "`www`" folder.

Now for the only tricky part.  Open the `jtv-calendar-widget` folder on your FTP server, and you'll find a "`cache`" folder.  This is where the widget caches data from Google and the Justin.TV API (don't worry, it's only one or two small text files).  You need to set the permissions of this folder on your FTP server so the PHP script can read *and* write to it.  Most FTP apps have an easy way to do this.  Click on the "`cache`" folder, and look for a "Set Permmissions", "Set Privileges" or "Get Info" button or menu item.  The easiest way is to set it so *everyone* can read and write to the folder.  However, you might want to read this article first, which has very detailed instructions on doing this: <http://codex.wordpress.org/Changing_File_Permissions>

Next, make sure this URL works:

	http://_YOUR_DOMAIN_NAME/jtv-calendar-widget/test.html

Change `_YOUR_DOMAIN_NAME_` to your own domain name you use to access your website, and make sure the test page loads.  If you get a "File Not Found" error, then something went wrong.  Check to make sure you uploaded the folder to the correct location on your server, and you constructed the URL correctly.

If the page loads, and you see a live JTV stream, but you also get a "*Failed to save cache file to server...*" error, then something is wrong with the permissions on the `cache` folder.  Make sure you actually set the privileges so *everyone* can read and write to the folder.  The PHP script needs to write a small text file into this folder, and PHP scripts that run on the server have very limited permissions.  That is why we need to tweak the folder settings on the FTP server.

Once everything on the test page loads, and you see the calendar widget above the JTV player, then you are ready to customize and embed it on your own live page!

## Embedding The Widget

To embed the calendar widget on your live page, copy & paste this code snippet into your HTML source code, and place it just above the JTV player:

	<div id="gcal_widget" calendar="r8psn8mpajnfa2703k43l6o014" channel="twit" style="font-family:Arial; font-weight:bold; text-align:center; line-height:40px;" live_style="font-size:22px;" next_style="font-size:18px; color:#888;"></div>
	
	<script src="http://_YOUR_DOMAIN_HERE_/jtv-calendar-widget/widget.php"></script>

Now, you'll need to change some things before you save changes to your HTML file.  First, change the `channel="twit"` to your own Justin.TV Channel ID.  You can find this on your Justin.TV Channel URL.

Next, we need to point the widget at *your* Google Calendar.  To do this, change the `calendar="r8psn8mpajnfa2703k43l6o014"` to your own Google Calendar Public ID.  To find this ID, login to <https://calendar.google.com>, and locate your live show calendar on the left-hand sidebar.  Click on the little arrow icon, select "**Share this Calendar**", and make sure it is marked as "**Public**" (otherwise the widget cannot access it).  Save changes, then click on the arrow again and select "**Calendar Settings**".  On this dialog, you should see a section called "**Calendar Address**", and just to the right of that, something called "Calendar ID".  It's right next to the XML/ICAL/HTML buttons, and should look something like this:

	(Calendar ID: 8qtp9fbfosv49knd5hcpr6ljeg@group.calendar.google.com)

You want the first part of that ID, i.e. the "`8qtp9fbfosv49knd5hcpr6ljeg`" (or whatever yours looks like).  Copy that and paste it into the HTML code above, right where it says: `calendar="......"`.  So in this case, it would be: `calendar="8qtp9fbfosv49knd5hcpr6ljeg"` (but change it to match your own Calendar ID).

Finally, change the `_YOUR_DOMAIN_NAME_` to your own domain name you use to access your website.  That's it!

Save and reload your HTML page, and you should see the widget above your live stream.  If your channel is offline the widget should show the next upcoming show.  But if your channel is live it should show the currently scheduled show, or if no show is scheduled, it'll just show your Justin.TV channel "status" (you can set this on your JTV admin page).

## Customizing The Look and Feel

If you want to customize the look & feel of the fonts & colors, edit the "`style`" atttibute.  You can also separately control the text style when a show is live now, and when one is upcoming (not live).  These live/upcoming styles are applied in addition to the base "style".  Example:

	style="font-family:Arial; font-weight:bold; text-align:center; line-height:40px;"
	live_style="font-size:22px;"
	next_style="font-size:18px; color:#888;"

Have fun!
