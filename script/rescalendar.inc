<link rel="stylesheet" type="text/css" href="script/yui/calendar/calendar.css" />

<script type="text/javascript" src="script/yui/yahoo-dom-event/yahoo-dom-event.js"></script>
<script type="text/javascript" src="script/yui/calendar/calendar-min.js"></script>

<script type="text/javascript">
<!--

YAHOO.namespace("calendar");

YAHOO.calendar.init = function() {

	YAHOO.calendar.cal1 = new YAHOO.widget.Calendar("cal1","calContainer1");
	YAHOO.calendar.cal2 = new YAHOO.widget.Calendar("cal2","calContainer2");
	YAHOO.calendar.cal1.cfg.setProperty("START_WEEKDAY", "1"); 
	YAHOO.calendar.cal2.cfg.setProperty("START_WEEKDAY", "1"); 
	YAHOO.calendar.cal1.render();
	YAHOO.calendar.cal2.render();

	function handleCal1Button(e) {
		var containerDiv = YAHOO.util.Dom.get("calContainer1"); 
		
		if(containerDiv.style.display == "none"){
			updateCal("searchstart",YAHOO.calendar.cal1);
			YAHOO.calendar.cal1.show();
		}else{
			YAHOO.calendar.cal1.hide();
		}
	}
	
	function handleCal2Button(e) {
		var containerDiv = YAHOO.util.Dom.get("calContainer2"); 
		
		if(containerDiv.style.display == "none"){
			updateCal("searchend",YAHOO.calendar.cal2);
			YAHOO.calendar.cal2.show();
		}else{
			YAHOO.calendar.cal2.hide();
		}
	}
	// Listener to show the Calendar when the button is clicked
	YAHOO.util.Event.addListener("showcal1", "click", handleCal1Button);
	YAHOO.util.Event.addListener("showcal2", "click", handleCal2Button);
	YAHOO.calendar.cal1.hide();
	YAHOO.calendar.cal2.hide();
	
	function handleSelect1(type,args,obj) {
			var dates = args[0]; 
			var date = dates[0];
			var year = date[0], month = date[1], day = date[2];
			
			var txtDate1 = document.getElementById("searchstart");
			txtDate1.value = day + "." + month + "." + year;
		}

	function handleSelect2(type,args,obj) {
			var dates = args[0]; 
			var date = dates[0];
			var year = date[0], month = date[1], day = date[2];
			
			var txtDate1 = document.getElementById("searchend");
			txtDate1.value = day + "." + month + "." + year;
		}

	function updateCal(input,obj) {
            var txtDate1 = document.getElementById(input);
            if (txtDate1.value != "") {
				var date = txtDate1.value.split(".");
				obj.select(date[1] + "/" + date[0] + "/" + date[2]);
				obj.cfg.setProperty("pagedate", date[1] + "/" + date[2]);
				obj.render();
            }
        }
	YAHOO.calendar.cal1.selectEvent.subscribe(handleSelect1, YAHOO.calendar.cal1, true);
	YAHOO.calendar.cal2.selectEvent.subscribe(handleSelect2, YAHOO.calendar.cal2, true);
}
YAHOO.util.Event.onDOMReady(YAHOO.calendar.init);
//-->
</script>
