// Google Calendar Event Display
// Displays current event from Google Calendar
// Copyright (c) 2011 Joseph Huckaby
// Released under the MIT License

(function gcal_widget() {
	var months = ["January", "February", "March", "April", "May", "June",
			"July", "August", "September", "October", "November", "December"];

	var weekdays = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
	
	var cal_id = '';
	var channel_id = '';
	var refresh_time = 61;
	var div = null;
	
	var live_style = '';
	var next_style = '';
	
	var live_prefix = "Live Now: ";
	var next_prefix = "Next Broadcast: ";
	
	var el = function el(id) { return document.getElementById(id); };
	var load_script = function load_script(url) {
		var scr = document.createElement('SCRIPT');
		scr.type = 'text/javascript';
		scr.src = url;
		document.getElementsByTagName('HEAD')[0].appendChild(scr);
	};
	
	var gcalw_response = window.gcalw_response = function gcalw_response(data) {
		var html = '';
		if (data.Code) {
			html += '<center><font size="+2" color="red" style="line-height:30px;"><b>ERROR: '+data.Description+'</b></font></center>';
			div.style.display = 'block';
		}
		else {
			if (data.CurrentEvent) {
				html += '<span style="'+live_style+'">'+live_prefix+' '+data.CurrentEvent.Title+'</span>';
			}
			else if (data.JTVLive && data.JTVStatus) {
				html += '<span style="'+live_style+'">'+live_prefix+' '+data.JTVStatus+'</span>';
			}
			else if (data.NextEvent) {
				var now = new Date();
				var when = new Date( data.NextEvent.CurrentStart * 1000 );
				html += '<span style="'+next_style+'">'+next_prefix+' '+data.NextEvent.Title;
				
				var now_daycode = now.getFullYear() + '-' + now.getMonth() + '-' + now.getDate();
				var when_daycode = when.getFullYear() + '-' + when.getMonth() + '-' + when.getDate();
				if (now_daycode != when_daycode) {
					// not today
					html += ' on ';
					var now_epoch = now.getTime() / 1000;
					if (data.NextEvent.CurrentStart - now_epoch < 86400 * 7) {
						// less than a week away
						html += weekdays[ when.getDay() ];
					}
					else {
						// more than a week away
						html += weekdays[ when.getDay() ] + ', ' + months[ when.getMonth() ] + ' ' + when.getDate();
					}
				}
				
				// time of day
				var hour = when.getHours();
				var ampm = 'AM';
				html += ' at ';
				if (hour >= 12) {
					ampm = 'PM';
					hour -= 12;
					if (!hour) hour = 12;
				}
				html += hour;
				if (when.getMinutes() > 0) {
					var mm = when.getMinutes();
					if (mm < 10) mm = '0' + mm;
					html += ':' + mm;
				}
				html += ' ' + ampm;
				html += '</span>';
			}
			else {
				// no events found
				html += '<span style="'+next_style+'">'+next_prefix+' ';
				html += 'Nothing scheduled, but check back soon!';
				html += '</span>';
			}
		}
		div.innerHTML = html;
		
		setTimeout( window.gcalw_refresh, refresh_time * 1000 );
	};
	var refresh = window.gcalw_refresh = function refresh() {
		var url = gcal_widget_data_url + '?cal='+cal_id+'&format=js&callback=gcalw_response&random=' + Math.random();
		if (channel_id) url += '&channel=' + channel_id;
		load_script(url);
	};
	var init = function init() {
		if (!div) return alert("GCalWidget: DOM Element Not Found: gcal_widget");
		cal_id = div.getAttribute('calendar') || alert("GCalWidget: DOM Element is missing 'calendar' attribute.");
		if (!window.gcal_widget_data_url) return alert("window.gcal_widget_data_url was not found.");
		
		channel_id = div.getAttribute('channel');
		
		live_style = div.getAttribute('live_style') || '';
		next_style = div.getAttribute('next_style') || '';
		
		live_prefix = div.getAttribute('live_prefix') || "Live Now: ";
		next_prefix = div.getAttribute('next_prefix') || "Next Broadcast: ";
		
		refresh();
	};
	
	// locate our element and go
	div = el('gcal_widget');
	if (div) init();
	else setTimeout( function() {
		div = el('gcal_widget');
		init();
	}, 1 );
})();
