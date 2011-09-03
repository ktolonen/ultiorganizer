<script type="text/javascript" src="script/yui/yahoo-dom-event/yahoo-dom-event.js"></script>
<script type="text/javascript" src="script/yui/container/container-min.js"></script> 
<script type="text/javascript">
<!--
    YAHOO.namespace("help.container");

    YAHOO.util.Event.onDOMReady(function () {

        // Remove progressively enhanced content class, just before creating the module
        YAHOO.util.Dom.removeClass("helptext", "yui-pe-content");

        YAHOO.help.container.helptext = new YAHOO.widget.Module("helptext", { visible: false });
        YAHOO.help.container.helptext.render();

        YAHOO.util.Event.addListener("helpbutton", "click", handleHelpButton);
    });

	
function handleHelpButton(e) {
		var containerDiv = YAHOO.util.Dom.get("helptext"); 
		
		if(containerDiv.style.display == "none"){
			YAHOO.help.container.helptext.show();
		}else{
			YAHOO.help.container.helptext.hide();
		}
	}
//-->
</script>