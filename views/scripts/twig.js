const timberstrap = true;
    
function filterPreviewHTML(input) {
    // Fix stretched links
    input = input.replace(/stretched-link/g, "");
    // if there is window.active_selector
   

    if (lc_editor_post_type == "lc_dynamic_template") {
        console.log('Dynamic template');
        // Wrap lc_ shortcodes
        input = input.replace(/\[lc_([^\]]+)\]/g, "<lc-dynamic-element hidden>[$&");
        input = input.replace(/\[\/lc_([^\]]+)\]/g, "$&</lc-dynamic-element>");
        // replace &amp;&amp; with &&
        input = input.replace(/&amp;&amp;/g, "&&");

        // remove [twig] tags
        checkQueries()
        processTwigElements(input);
        updateEditorStates();
    } else {
        console.log('Not a dynamic template');
        console.log(input.includes('&&'), '&&');
        // replace &amp;&amp; with &&
        input = input.replace(/&amp;&amp;/g, "&&");
        checkQueries()
        processTwigElements(input);
        updateEditorStates();
        
    }
    return input;
}

function updateEditorStates() {
     if (window.active_selector) {
        const frame = document.getElementById('previewiframe');
        const iframe = frame.contentDocument || frame.contentWindow.document;
        
        // Get the active selector
        var active_selector = iframe.querySelector(window.active_selector);

        // If it has classes Array.from class list, otherwise empty array 
        const classes = active_selector ? Array.from(active_selector.classList) : [];

        setManagerSession(active_selector, classes);
    }
}

function render_dynamic_content(selector){
	if (lc_editor_post_type == "lc_dynamic_template") {
        console.log('Dynamic Rendering')
		render_dynamic_templating_shortcodes_in(selector);
        
    } else {
        console.log('Broke Dynamic Rendering Twig')
		render_shortcodes_in(selector);
  
    }
}
