window.global_autocompletes = [];
window.global_snippets = [];
window.active_multiselect = [];
window.context = {};
window.context_completions = [];
window.selected_context = [];
window.active_selector = null;
window.active_el_tag = null;

window.winbox_width = 375;


// Check to see why it's not recognitizing the document.addEventListener

// Function to generate a unique selector for an element
function getSelector(element) {
  if (element.id) {
    return '#' + element.id;
  } else if (element.classList.length > 0) {
    return '.' + element.classList[0];
  } else {
    return element.tagName.toLowerCase();
  }
}

function removeBuilderClasses(classes) {
  const builderClasses = [
    'lc-block',
    'lc-block-inner',
    'lc-highlight-block',
    'lc-highlight-currently-editing',
    'lc-highlight-mainpart',
  ];
  // classes is a string with each class on a new line
  return classes.split('\n').filter(className => !builderClasses.includes(className)).join('\n');
}

function formatClasses(classes) {
  // Split classes into an array
  const classArray = classes.split(' ');

  // Group classes by prefix
  const groupedClasses = {
    'non-prefixed': []
  };
  classArray.forEach(className => {
    const parts = className.split(':');
    if (parts.length > 1) {
      const prefix = parts[0];
      if (!groupedClasses[prefix]) {
        groupedClasses[prefix] = [];
      }
      groupedClasses[prefix].push(className);
    } else {
      groupedClasses['non-prefixed'].push(className);
    }
  });

  // Join classes with spaces between prefixes
  let formattedClasses = groupedClasses['non-prefixed'].join('\n') + '\n\n';
  delete groupedClasses['non-prefixed'];

  Object.entries(groupedClasses).forEach(([prefix, group]) => {
    formattedClasses += group.join('\n') + '\n\n';
  });

  return removeBuilderClasses(formattedClasses.trim());
}

function organizeSiulaClasses(classes) {
  const frame = document.getElementById('previewiframe');
  // Ensure the frame exists
  if (!frame) {
    console.error('Preview iframe not found');
    return formatClasses(classes);
  }

  // Content Window
  const cw = frame.contentWindow;
  if (!cw || !cw.siul || !cw.siul.module) {
    console.error('siul module not found in Content Window');
    return formatClasses(classes);
  }

  const siulaClasses = formatClasses(cw.siul.module.classSorter.sort(classes));
  return siulaClasses;
}


// function reManageTreeSession() {
//   const selector = window.active_selector;
//   const classes = window.active_selector_classes;

//     document.getElementById('tree-expand-all').click()

//   // remove all data-active-item attributes that aren't selector or in active_multiselect
//   // Get all data-active-item, then check if they are in active_multiselect
//   const activeItems = document.querySelectorAll('[data-active-item]');
//   activeItems.forEach(item => {
//     const itemSelector = item.getAttribute('data-active-item');
//     if (itemSelector !== selector && !window.active_multiselect.includes(itemSelector)) {
//       item.removeAttribute('data-active-item');
//     }
//   });

//   // for each item in active_multiselect, add data-active-item attribute to the clicked item
//   window.active_multiselect.forEach(item => {
//     console.log('Tree Active Multiselect:', item);
//     const treeViewItem = document.querySelector(`.tree-view-item[data-selector="${item}"] .tree-view-item-content-wrapper`);
//     console.log('Tree View Item:', treeViewItem)
//     if (treeViewItem) {
//       treeViewItem.setAttribute('data-active-item', item);
//     }
//   });

//   // now scroll #tree-body to the data-active-item that is selector
// // now scroll #tree-body to the data-active-item that is selector
// const treeBody = document.getElementById('tree-body');
// const activeItem = document.querySelector(`.tree-view-item[data-selector="${selector}"] .tree-view-item-content-wrapper`);

// // Scroll to the active item smoothly and position it in the middle
// if (activeItem) {
//   const activeItemRect = activeItem.getBoundingClientRect();
//   const treeBodyRect = treeBody.getBoundingClientRect();
//   const activeItemCenter = activeItemRect.top + activeItemRect.height / 2;
//   const treeBodyCenter = treeBodyRect.top + treeBodyRect.height / 2;
//   const scrollPosition = activeItemCenter - treeBodyCenter + treeBody.scrollTop;

//   treeBody.scrollTo({
//     top: scrollPosition,
//     behavior: 'smooth'
//   });
// }

// }


// function manageTreeSession(selector, classes) {
//   // expand tree view 
//   document.getElementById('tree-expand-all').click()

//   // remove all data-active-item attributes that aren't selector or in active_multiselect
//   // Get all data-active-item, then check if they are in active_multiselect
//   const activeItems = document.querySelectorAll('[data-active-item]');
//   activeItems.forEach(item => {
//     const itemSelector = item.getAttribute('data-active-item');
//     if (itemSelector !== selector && !window.active_multiselect.includes(itemSelector)) {
//       item.removeAttribute('data-active-item');
//     }
//   });

//   // for each item in active_multiselect, add data-active-item attribute to the clicked item
//   window.active_multiselect.forEach(item => {
//     console.log('Tree Active Multiselect:', item);
//     const treeViewItem = document.querySelector(`.tree-view-item[data-selector="${item}"] .tree-view-item-content-wrapper`);
//     console.log('Tree View Item:', treeViewItem)
//     if (treeViewItem) {
//       treeViewItem.setAttribute('data-active-item', item);
//     }
//   });

//   // now scroll #tree-body to the data-active-item that is selector
// // now scroll #tree-body to the data-active-item that is selector
// const treeBody = document.getElementById('tree-body');
// const activeItem = document.querySelector(`.tree-view-item[data-selector="${selector}"] .tree-view-item-content-wrapper`);

// // Scroll to the active item smoothly and position it in the middle
// if (activeItem) {
//   const activeItemRect = activeItem.getBoundingClientRect();
//   const treeBodyRect = treeBody.getBoundingClientRect();
//   const activeItemCenter = activeItemRect.top + activeItemRect.height / 2;
//   const treeBodyCenter = treeBodyRect.top + treeBodyRect.height / 2;
//   const scrollPosition = activeItemCenter - treeBodyCenter + treeBody.scrollTop;

//   treeBody.scrollTo({
//     top: scrollPosition,
//     behavior: 'smooth'
//   });
// }
 

// }

// add data-active-item to all selectors in window.active_multiselect
function updatePreviewActiveItems() {
  const preview = document.getElementById('previewiframe');
  const previewDoc = preview.contentDocument || preview.contentWindow.document;
  window.active_multiselect.forEach(selector => {
    const previewElement = previewDoc.querySelector(selector);
    if (previewElement) {
      previewElement.setAttribute('data-active-item', selector);
    }
  });
}

function decodeEntities(encodedString) {
  const textarea = document.createElement('textarea');
  textarea.innerHTML = encodedString;
  return textarea.value;
}

function generateAttributesContext(element) {
  // Take the element and add its attributes (excluding class, id with value 'lc-main', and certain other attributes) as an array of key-value pairs to window.selected_context
  const attributes = element.attributes;
  const attributeArray = [];

  const excludedAttributes = [
    'class',
    'src',
    'srcset',
    'href',
    'alt',
    'title',
    'type',
    'rel',
    'target',
    'width',
    'height',
    'style',
    'data-src',
    'data-srcset',
    'sizes',
    'media',
    'poster',
    'preload',
    'autoplay',
    'loop',
    'muted',
    'controls',
    'playsinline',
    'readonly',
    'disabled',
    'checked',
    'selected',
    'multiple',
    'required',
    'placeholder',
    'autocomplete',
    'autofocus',
    'min',
    'max',
    'step',
    'pattern',
    'maxlength',
    'minlength',
    'size',
    'rows',
    'cols'
  ];

  for (let i = 0; i < attributes.length; i++) {
    const attr = attributes[i];
    if (!excludedAttributes.includes(attr.name) && !(attr.name === 'id' && attr.value === 'lc-main')) {
      // Decode the attribute value
      const decodedValue = decodeEntities(attr.value);
      attributeArray.push({ key: attr.name, value: decodedValue });
    }
  }

  window.selected_context = attributeArray;

  // Update the attribute count span
  const attributeCountSpan = document.querySelector('.attribute_count');
  if (attributeCountSpan) {
    const attributeCount = attributeArray.length;
    if (attributeCount === 0) {
      attributeCountSpan.textContent = '';
    } else {
      attributeCountSpan.textContent = attributeCount.toString();
    }
  }

  // Update the plain attributes editor
  updatePlainAttributesEditor();
}


function updatePlainAttributesEditor() {
  const attributeList = window.selected_context.map(attr => {
    const encodedValue = attr.value;
    return `${attr.key}="${encodedValue}"`;
  }).join('\n\n---\n\n');  // Add a double newline to create a blank line between attributes

  attributesEditor.setValue(attributeList, -1);
}

function setManagerSession(targetElement, classes) {
  const el_selector = CSSelector(targetElement);
  // Update the active_selector and active_selector_classes in the parent window
  window.active_multiselect = [el_selector];
  window.active_selector = el_selector;
  window.active_selector_classes = classes;
  window.active_attribute = null;
  window.active_el_tag = null;

 
   
    var trueElement = doc.querySelector(el_selector);
    // Check if trueElement exists and is either main#lc-main or a descendant of it
    // Set window.active_el_tag to the tag name of the trueElement
    if (trueElement && trueElement.closest('main#lc-main')) {
      AgnosticState.active_el_tag = trueElement.tagName.toLowerCase();
    }

    // Set outerHTML to the editor
    trueElement.outerHTML = html_beautify(trueElement.outerHTML);
    // replace &amp;&amp; with &&
    const cleanHTML = prettyTwig(trueElement.innerHTML);
    window.innerHTMLEditor.setValue(cleanHTML);
    

    generateAttributesContext(trueElement);

    // Check if the sidebar is relevant
    checkRelevantSidebar(el_selector);

    const organize = organizeSiulaClasses(classes.join(' '));
    
    // Update Selection Menu
    classManagerEditor.session.setValue(organize);

    console.log('Active Selector:', el_selector);
    console.log('Active Selector Classes:', classes);
    console.log('MultiSelect:', window.active_multiselect);

    // Add data-active-item attribute to the clicked item
    targetElement.setAttribute('data-active-item', el_selector);


    // Remove all empty classes from elements class> class="" or class=" " from the previewElements 
    const preview = document.getElementById('previewiframe');
    const previewDoc = preview.contentDocument || preview.contentWindow.document;
    const previewElements = previewDoc.querySelectorAll('*');
    previewElements.forEach(element => {
      if (element.classList.length === 0) {
        element.removeAttribute('class');
      }
    });
  
  setTimeout(() => {
    // reManageTreeSession();
  }, 10);
}



function checkRelevantSidebar(selector) {
  // get the document's #sidepanel then loop over its children to check if any of them don't contain style="display: none;"
  const $sidepanel = $('#sidepanel');
  if (!$sidepanel.length) {
    return;
  }

  let sidebarOpen = false;

  $sidepanel.children().each(function() {
    if (!$(this).attr('style') || !$(this).attr('style').includes('display: none;')) {
      // if it does, check [selector] attribute to see if it matches the targetEl selector
      if ($(this).attr('selector') === selector) {
        // if it does, leave sidebar open
        sidebarOpen = true;
        return false; // break out of the loop
      }
    }
  });

  // otherwise $(".close-sidepanel").click();
  if (!sidebarOpen) {
    $('.close-sidepanel').click();
  }
}

// Function to attach the event listener to the previewiframe
function attachIframeClickListener(iframe) {
  iframe.contentDocument.addEventListener('click', function(event) {
    const targetElement = event.target;
    const selector = getSelector(targetElement);
    const classes = Array.from(targetElement.classList);
    
    const check = targetElement.closest('main#lc-main');
   
    
    // if (check) {
      const isDebugManager = targetElement.closest('#debug-manager') !== null;
      
      if (!isDebugManager && (!classes.includes('live-shortcode') || targetElement.getAttribute('lc-helper') === 'posts-loop' || !classes.includes('lc-rendered-shortcode-wrap'))) {
        if (event.metaKey || event.ctrlKey) {
          // user-select none to main#lc-main
          console.log('Shift Click Event:', selector);
          window.active_multiselect.push(CSSelector(targetElement));
          console.log('Active Multiselect:', window.active_multiselect);
          
          if (targetElement.hasAttribute('data-active-item')) {
            targetElement.removeAttribute('data-active-item');
            // remove user-select none to main#lc-main
          } else {
            // remove all 
            targetElement.setAttribute('data-active-item', CSSelector(selector));
            targetElement.removeAttribute('data-hover-item');
            // remove user-select none to main#lc-main
          }
        } else {
          // remove all previous active items
          iframe.contentDocument.querySelectorAll('[data-active-item]').forEach(item => {
            item.removeAttribute('data-active-item');
          });
          //  window.tweaks.restore();
          console.log('Click Event:', selector);
          window.active_multiselect = [CSSelector(targetElement)];
          setManagerSession(targetElement, classes);
        }
      }
    // }
  });

  iframe.contentDocument.addEventListener('mouseover', function(event) {
    const targetElement = event.target;
    const selector = getSelector(targetElement);
    const classes = Array.from(targetElement.classList);

    const check = targetElement.closest('main#lc-main');

    if (check) {
      const isDebugManager = targetElement.closest('#debug-manager') !== null;
      
      if (!isDebugManager && (!classes.includes('live-shortcode') || targetElement.getAttribute('lc-helper') === 'posts-loop' || !classes.includes('lc-rendered-shortcode-wrap'))) {
        window.parent.hover_selector = selector;
        window.parent.hover_selector_classes = classes;

        if (!targetElement.hasAttribute('data-active-item')) {
          targetElement.setAttribute('data-hover-item', selector);
        }
        
        targetElement.removeAttribute('editable');

        if (targetElement.getAttribute('lc-helper') !== 'posts-loop' || !classes.includes('lc-rendered-shortcode-wrap') || !classes.includes('live-shortcode')) {
          targetElement.removeAttribute('lc-helper');
        }
      }
    }
  });

  iframe.contentDocument.addEventListener('mouseout', function(event) {
    const targetElement = event.target;
    const classes = Array.from(targetElement.classList);

    const check = targetElement.closest('main#lc-main');

    if (check) {
      const isDebugManager = targetElement.closest('#debug-manager') !== null;
      
      if (!isDebugManager && (targetElement.getAttribute('lc-helper') !== 'posts-loop' || !classes.includes('lc-rendered-shortcode-wrap') || !classes.includes('live-shortcode'))) {
        const previouslyHoveredItem = iframe.contentDocument.querySelector('[data-hover-item]');
        if (previouslyHoveredItem) {
          previouslyHoveredItem.removeAttribute('data-hover-item');
        }
      }
    }
  });
}

// // Function to inject the CSS into the iframe's head
function injectCSS(iframe) {
  const css = `

lc-dynamic-twig {
  display: block!important;
} 



  `;

  const style = iframe.contentDocument.createElement('style');
  style.textContent = css;
  iframe.contentDocument.head.appendChild(style);

}



async function getACFsnippets(manager) {
  const request = await fetch('/wp-json/acf/v1/fields', {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
    },
  });

  const completions = [];
  const data = await request.json();

  function generateCompletion(field, tier, fullPath) {
    const fieldName = field.name;
    const namePrefix = `code/acf/${fullPath}`;
    let completion = {};

    if (field.type === 'repeater') {
      const subCompletions = field.sub_fields.map((subField) => {
        const subFieldName = subField.name;
        const subFieldCompletion = generateCompletion(subField, tier === 'post' ? 'item' : 'nested', `${fullPath}/${subFieldName}`);
        return subFieldCompletion.content;
      }).join('\n');

      completion = {
        name: `${namePrefix}:repeater`,
        score: 100,
        content: `
{# Check if the repeater field "${fieldName}" has any data #}
{% if ${tier === 'post' ? `${tier}.meta('${fieldName}')` : `${tier}.${fieldName}`} %}
  <div>
    {# Loop through each item in the repeater field #}
    {% for ${tier === 'post' ? 'item' : 'nested'} in ${tier === 'post' ? `${tier}.meta('${fieldName}')` : `${tier}.${fieldName}`} %}
${subCompletions
  .split('\n')
  .map(line => `      ${line}`)
  .join('\n')}
      $0
    {% endfor %}
  </div>
{% endif %}
`,
      };
    } else if (field.type === 'flexible_content') {
      const subCompletions = field.layouts.map((layout) => {
        const layoutName = layout.name;
        const layoutSubFields = layout.sub_fields.map((subField) => {
          const subFieldName = subField.name;
          const subFieldCompletion = generateCompletion(subField, tier === 'post' ? 'item' : 'nested', `${fullPath}/${layoutName}/${subFieldName}`);
          return subFieldCompletion.content;
        }).join('\n');

        return `
{# Check if the current layout is "${layoutName}" #}
{% if ${tier === 'post' ? 'item' : 'nested'}.acf_fc_layout == '${layoutName}' %}
${layoutSubFields
  .split('\n')
  .map(line => `  ${line}`)
  .join('\n')}
  $0
{% endif %}
`;
      }).join('');

      completion = {
        name: `${namePrefix}:flexible_content`,
        score: 100,
        content: `
{# Check if the flexible content field "${fieldName}" has any data #}
{% if ${tier === 'post' ? `${tier}.meta('${fieldName}')` : `${tier}.${fieldName}`} %}
  <div>
    {# Loop through each item in the flexible content field #}
    {% for ${tier === 'post' ? 'item' : 'nested'} in ${tier === 'post' ? `${tier}.meta('${fieldName}')` : `${tier}.${fieldName}`} %}
${subCompletions
  .split('\n')
  .map(line => `      ${line}`)
  .join('\n')}
    {% endfor %}
  </div>
{% endif %}
`,
      };
    } else if (field.type === 'group') {
      const subCompletions = field.sub_fields.map((subField) => {
        const subFieldName = subField.name;
        const subFieldCompletion = generateCompletion(subField, fieldName, `${fullPath}/${subFieldName}`);
        return subFieldCompletion.content;
      }).join('\n');

      completion = {
        name: `${namePrefix}:group`,
        score: 100,
        content: `
{# Check if the group field "${fieldName}" has any data #}
{% if ${tier === 'post' ? `${tier}.meta('${fieldName}')` : `${tier}.${fieldName}`} %}
  {# Set a variable for the group field data #}
  {% set ${fieldName} = ${tier === 'post' ? `${tier}.meta('${fieldName}')` : `${tier}.${fieldName}`} %}
${subCompletions
  .split('\n')
  .map(line => `  ${line}`)
  .join('\n')}
  $0
{% endif %}
`,
      };
    } else if (field.type === 'image') {
      const fieldAccessor = tier === 'post' ? `${tier}.meta('${fieldName}')` : `${tier}.${fieldName}`;
      completion = {
        name: `${namePrefix}:image`,
        score: 100,
        content: `
{# Check if the image field "${fieldName}" has any data #}
{% if ${fieldAccessor} %}
  {# Use TimberImage to get the optimized image URL #}
  <img src="{{ TimberImage(${fieldAccessor}).src }}">
  $0
{% endif %}
`,
      };
    } else if (field.type === 'gallery') {
      completion = {
        name: `${namePrefix}:gallery`,
        score: 100,
        content: `
{# Check if the gallery field "${fieldName}" has any data #}
{% if ${tier}.${fieldName} %}
  <div>
    {# Loop through each image in the gallery field #}
    {% for item in ${tier}.${fieldName} %}
      {# Use TimberImage to get the optimized image URL #}
      <img src="{{ TimberImage(item).src }}">
    {% endfor %}
  </div>
  $0
{% endif %}
`,
      };
    } else {
      const fieldAccessor = tier === 'post' ? `${tier}.meta('${fieldName}')` : `${tier}.${fieldName}`;
      completion = {
        name: `${namePrefix}:field`,
        score: 100,
        content: `
{# Check if the field "${fieldName}" has any data #}
{% if ${fieldAccessor} %}
  {# Output the field value #}
  {{ ${fieldAccessor} }}
  $0
{% endif %}
`,
      };
    }

    return completion;
  }

  function registerField(field, tier = 'post', parentPath = '') {
    const fieldName = field.name;
    const fullPath = parentPath ? `${parentPath}/${fieldName}` : fieldName;
    completions.push(generateCompletion(field, tier, fullPath));

    if (field.sub_fields) {
      field.sub_fields.forEach((subField) => {
        const subTier = field.type === 'repeater' ? (tier === 'post' ? 'item' : 'nested') : tier;
        registerField(subField, subTier, fullPath);
      });
    }
  }

  data.forEach((field) => registerField(field, 'post'));
  manager.register(completions, 'twig');
}


async function registerThemeSnippets(manager) {
  console.log('Registering theme snippets');
  let snippet_form = {
    name: null,
    score: 100,
    content: null
  };

  const snippets = [];

  // Get data from lc/v1/blocks endpoint
  try {
    const blocksResponse = await fetch('/wp-json/lc/v1/blocks');
    const blocksData = await blocksResponse.json();

    // Loop over each block item and add them in snippet form
    blocksData.forEach(block => {
      const { name, category, component, content } = block;
      const snippetName = `block/${category}/${name}`;
      const snippet = { ...snippet_form, name: snippetName, content: component };
      snippets.push(snippet);
    });
  } catch (error) {
    console.error('Error fetching blocks:', error);
  }

   try {
    const alpineResponse = await fetch('/wp-json/lc/v1/alpine');
    const alpineData = await alpineResponse.json();

    // Loop over each block item and add them in snippet form
    alpineData.forEach(block => {
      const { name, category, component, content } = block;
      const snippetName = `alpine/${category}/${name}`;
      const snippet = { ...snippet_form, name: snippetName, content: content };
      snippets.push(snippet);
    });
  } catch (error) {
    console.error('Error fetching blocks:', error);
  }

  try {
  const menusResponse = await fetch('/wp-json/lc/v1/menu-names');
  const menuNames = await menusResponse.json();

  // Loop over each menu name and add them in snippet form
  menuNames.forEach(menuName => {
    const snippetName = `code/menu/${menuName}`;
    const snippetContent = `{% for item in menu('${menuName}').items %}
<a href="{{item.link}}">{{item.title}}</a>
{% endfor %}`;
    const snippet = { ...snippet_form, name: snippetName, content: snippetContent };
    snippets.push(snippet);
  });
} catch (error) {
  console.error('Error fetching menu names:', error);
}

   try {
    const sectionsResponse = await fetch('/wp-json/lc/v1/sections');
    const sectionsData = await sectionsResponse.json();

    // Loop over each block item and add them in snippet form
    sectionsData.forEach(block => {
      const { name, category, component, content } = block;
      const snippetName = `section/${category}/${name}`;
      const snippet = { ...snippet_form, name: snippetName, content: component };
      snippets.push(snippet);
    });
  } catch (error) {
    console.error('Error fetching sections:', error);
  }

  try {
    const partialsResponse = await fetch('/wp-json/lc/v1/partials');
    const partialsData = await partialsResponse.json();

    // Loop over each block item and add them in snippet form
    partialsData.forEach(block => {
      const { name, category, component, content } = block;
      const snippetName = `partials/${category}/${name}`;
      const snippet = { ...snippet_form, name: snippetName, content: content };
      snippets.push(snippet);
    });
  } catch (error) {
    console.error('Error fetching partials:', error);
  }

  // Get data from lc/v1/svgs endpoint
  try {
    const svgsResponse = await fetch('/wp-json/lc/v1/svgs');
    const svgsData = await svgsResponse.json();

    // Loop over each SVG item and add them in snippet form
    svgsData.forEach(svg => {
      const { name, category, content, component } = svg;
      const snippetName = `svg/${category}/${name}`;
      const snippet = { ...snippet_form, name: snippetName, content: content };
      snippets.push(snippet);
    });
    svgsData.forEach(svg => {
      const { name, category, content, component } = svg;
      const snippetName = `include/svg/${category}/${name}`;
      const snippet = { ...snippet_form, name: snippetName, content: component };
      snippets.push(snippet);
    });
  } catch (error) {
    console.error('Error fetching SVGs:', error);
  }

  // Add more endpoints as needed (e.g., lc/v1/etc)

  // Console.log all the snippets
  await manager.register(snippets, 'twig');
}


function updatePluginOptions() {
  window.lc_html_editor = ace.edit('lc-html-editor');
  window.lc_css_editor = ace.edit('lc-css-editor');

  // Update HTML to TWIG
  lc_html_editor.session.setMode('ace/mode/twig');

  // Update to monokai theme
  lc_html_editor.setTheme('ace/theme/tomorrow_night_bright');

  // Enable snippets
  lc_html_editor.setOptions({
    enableBasicAutocompletion: true,
    enableSnippets: true,
    enableLiveAutocompletion: true,
  });

  // Enable Twig Snippets
  const manager = ace.require('ace/snippets').snippetManager;
  // getTwigSnippets(manager);
  getACFsnippets(manager);
  registerThemeSnippets(manager);
}


function monitorIframeChanges(iframe) {
  const iframeDocument = iframe.contentDocument || iframe.contentWindow.document;

  // Create a MutationObserver to watch for changes in the iframe's DOM
  const observer = new MutationObserver((mutationsList, observer) => {
    // Run checkQueries whenever a change is detected
    checkQueries();
  });

  // Configuration of the observer
  const config = {
    childList: true, // Watch for additions or removals of child nodes
    subtree: true    // Watch the entire subtree
  };
  console.log('iframe change');
  // Start observing the target node (iframe's body)
  observer.observe(iframeDocument.body, config);
}

function checkQueries() {
  // Use doc.querySelector to find all [lc-helper="posts-loop"] elements
  const postsLoops = doc.querySelectorAll('[lc-helper="posts-loop"]');
  console.log('Posts Loops:', postsLoops.length);

  // Get or create the container in the parent document
  let container = document.getElementById('query-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'query-container';
    // Create the header
    const header = document.createElement('div');
    header.id = 'query-header';
    header.textContent = 'Page Queries';
    container.appendChild(header);
    // Create the items container
    const itemsContainer = document.createElement('div');
    itemsContainer.id = 'query-items';
    container.appendChild(itemsContainer);
    document.body.appendChild(container);
  }


  // Get the query items container
  const itemsContainer = document.getElementById('query-items');

  // Clear the current queries
  itemsContainer.innerHTML = '';

  // Check if there are any queries
  if (postsLoops.length > 0) {
    // Show the container
    container.style.display = 'block';

    // Add new queries
    for (let i = 0; i < postsLoops.length; i++) {
      // Create a div element
      const div = document.createElement('div');
      // Extract output_dynamic_view_id from the shortcode
      const outputDynamicViewId = extractOutputDynamicViewId(postsLoops[i].innerHTML);
      // Format the output_dynamic_view_id or fallback to "Query: index"
      const displayName = outputDynamicViewId ? formatDisplayName(outputDynamicViewId) : `Query: ${i}`;
      // Set the text content
      div.textContent = displayName;

      // push to global autocompletes
      addCompletion(displayName, 'query');

      // Append the div to the items container
      itemsContainer.appendChild(div);
      // Set the data-query attribute to the index
      div.setAttribute('data-query', i);
      const selector = CSSelector(postsLoops[i]);
      div.setAttribute('data-query-selector', selector);
    }
  } else {
    // Hide the container
    container.style.display = 'none';
  }

  
}

function addCompletion(name, type) {

  switch (type) {
    case 'query':
      let field_name = name.split(' ').join('_').toLowerCase();
      let snippet = {
        name: `code/${type}/` + field_name,
        score: 100,
        content: `
          <div>
          {# This is a query for ${field_name} #}
            {% for item in ${field_name} %}
              <div>
                {{ item.title }}
              </div>
            {% endfor %}
          </div>`
      }

      let completion = {
        caption: `${type}/${field_name}`,
        score: 100,
        value: field_name,
        meta: 'query',
      }

      // Push to global completions and snippets
      window.global_autocompletes.push(completion);
      const manager = ace.require('ace/snippets').snippetManager;

      manager.register([snippet], 'twig');
      break;
    }

}

function extractOutputDynamicViewId(content) {
  const match = content.match(/output_dynamic_view_id\s*=\s*"([^"]+)"/);
  return match ? match[1] : null;
}

function formatDisplayName(name) {
  return name
    .split('_')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}


// Event listener for clicks on query-container > div
$(document).on('click', '#query-items > div', function() {
  const selector = $(this).attr('data-query-selector');
  const iframe = document.querySelector('#previewiframe');
  const iframeDocument = iframe.contentDocument || iframe.contentWindow.document;

  // Find and click the lc-helper element within the iframe
  const targetElement = iframeDocument.querySelector(selector);
  if (targetElement) {
    $(targetElement).trigger('click');
  } else {
    console.log(`Element with selector ${selector} not found in iframe`);
  }
});


// Renamed debounce function
function lcDebounce(func, delay) {
    let timeoutId;
    return function (...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            func.apply(this, args);
        }, delay);
    };
}

// Renamed debounced version of render_dynamic_templating_twig
const lcDebouncedRenderDynamicTwig = lcDebounce(render_dynamic_templating_twig, 300);

// Updated processTwigElements function
function processTwigElements() {
    const iframe = document.getElementById('previewiframe');
    // Get all lc-dynamic-twig elements within the iframe
    // Call the debounced version with the new name
    lcDebouncedRenderDynamicTwig();
}

function render_dynamic_templating_twig() {
  const iframe = document.getElementById('previewiframe');
  if (iframe && iframe.contentDocument) {
    const twigDocument = iframe.contentDocument;
    const element = twigDocument.querySelector('main#lc-main');
    const urlParams = new URLSearchParams(window.location.search);
    var storedSettings = JSON.parse(localStorage.getItem(window.current_post_title));
    console.log('Stored Settings:', storedSettings)

    if (element && element.innerHTML) {
      const trueElement = doc.querySelector('main#lc-main');
      if (!trueElement) {
        console.log('No main#lc-main element found in the parent document');
        return;
      }
      // Check if startViewTransition is available
      if (typeof twigDocument.startViewTransition === 'function') {
        const transition = twigDocument.startViewTransition(() => {
          const formData = new FormData();
          const the_html = trueElement.innerHTML.split('[twig]').join('').split('[/twig]').join('');
          const new_html = the_html.replace(/&amp;&amp;/g, '&&');
          trueElement.innerHTML = new_html;
          formData.append('action', 'lc_process_dynamic_templating_twig');
          formData.append('shortcode', new_html);
          formData.append('post_id', lc_editor_current_post_id);
          formData.append('demo_id', (urlParams.get('demo_id') ?? false));
          formData.append('settings', JSON.stringify(storedSettings));

          return fetch(lc_editor_saving_url, {
            method: 'POST',
            body: formData
          })
          .then(response => response.text())
          .then(response => {
            // Update the main element with the response
            twigDocument.querySelector('main#lc-main').innerHTML = response;

            setTimeout(() => {
              // This keeps inspector in sync with editor changes
              updateInspectorData();
              const stateDoc = iframe.contentDocument || iframe.contentWindow.document;
              const stateElement = stateDoc.querySelector('script#state-data');

              if (stateElement) {
                const parseState = JSON.parse(stateElement.textContent);
                const format = extractKeysAndValues(parseState);
                window.context = parseState;
                window.context_completions = format;
              } else {
                console.log('No State Data Found');
              }

            }, 10);
          })
          .catch(error => {
            console.error('Error:', error);
          });
        });

        // Ensure transition is finished
        if (transition && typeof transition.finish === 'function') {
          transition.finish();
        } else {
          console.log('Transition object does not have a finish method');
        }
      } else {
        console.log('startViewTransition is not a function');
      }
    } else {
      console.log('No Twig Elements Found', element);
    }
  } else {
    console.log('Failed to get iframe contentDocument');
  }
}


document.addEventListener('lcUpdatePreview', function(event) {
  console.log('lcUpdatePreview event fired')
  const iframe = document.getElementById('previewiframe');
  const twigElements = event.detail.twigElements; // Assuming the twigElements are passed in the event detail
  processTwigElements();
  checkQueries();
});

// Open the HTML editor
 function openPicoHTMLEditor() {
              $(".close-sidepanel").click();
              $(".lc-editor-close").click();
              $("body").addClass("lc-bottom-editor-is-shown");
              //$("main .lc-shortcode-preview").remove();
              $("#lc-html-editor-window").attr("selector", window.active_selector);
              myConsoleLog("Open html editor for: " + window.active_selector);
              var html = getPageHTML(window.active_selector);
              const cleanHTML = html.replace(/&amp;&amp;/g, '&&');
              set_html_editor(cleanHTML);
              $("#lc-html-editor-window").removeClass("lc-opacity-light").fadeIn(100);
              lc_html_editor.focus();
              $("#html-tab").click();
          
            // click lc-editor-slide using vanilla JS
            setTimeout(() => {
             // click a.lc-editor-slide
              document.querySelector('a.lc-editor-side').click()
            }, 10);
        }

function sortPicoClasses() {
            var classes = classManagerEditor.getValue();
            classes.split('\n').join(' ');

            const organize = organizeSiulaClasses(classes);

            // Update the editor
            classManagerEditor.setValue(organize);
        }

// Listen to the document for whenever selector= attribute changes and console.log the changes
// Create a named function for the observer callback
function selectorAttributeObserverCallback(mutations) {
  mutations.forEach((mutation) => {
    if (mutation.type === 'attributes' && mutation.attributeName === 'selector') {
      const changedElement = mutation.target;
      const elementId = changedElement.id ? `ID: ${changedElement.id}` : 'No ID';
      const elementClasses = changedElement.classList.length > 0 ? `Classes: ${Array.from(changedElement.classList).join(', ')}` : 'No Classes';
      // do not select live-shortcode elements or lc-helper="posts-loop"
      if (changedElement.classList.contains('live-shortcode') || changedElement.getAttribute('lc-helper') === 'posts-loop') {
      console.log(`Selector attribute changed:`);
      console.log(`  Element: ${changedElement.tagName}`);
      console.log(`  Item: ${changedElement.getAttribute('item-type')}`);
      console.log(`  ${elementId}`);
      console.log(`  ${elementClasses}`);
      console.log(`  Selector: ${changedElement.getAttribute('selector')}`);

      // Get the iframe of the selector
      const previewEl = doc.querySelector(changedElement.getAttribute('selector'));
      // Get the classes of the selector
      const classes = Array.from(previewEl.classList);
      // Set manager session
      
      console.log('Preview Element:', previewEl);

      setManagerSession(previewEl, classes);
      }
    }
  });
}

// Create a MutationObserver instance with a unique name
const selectorAttributeObserver = new MutationObserver(selectorAttributeObserverCallback);

// Configure the observer options
const observerOptions = {
  attributes: true, // Listen for attribute changes
  attributeFilter: ['selector'], // Only observe changes to the 'selector' attribute
  subtree: true // Observe changes in the entire document subtree
};

// Start observing the document with the specified options
selectorAttributeObserver.observe(document, observerOptions);

// Simple debounce function
function debounce(func, wait) {
  let timeout;
  return function(...args) {
    const later = () => {
      clearTimeout(timeout);
      func.apply(this, args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// Debounced version of setManagerSession
const debouncedSetManagerSession = debounce(setManagerSession, 300); // Adjust the debounce wait time as needed


// // Function to attach the event listener to the tree-view-item
// function attachTreeViewItemListener() {
//   // First remove all other listenrs from the tree-body
//   detachTreeViewItemListener();
//   $('#tree-body').on('click', '.tree-view-item', function(event) {
//     // console.log the element being clicked 
//     reManageTreeSession();
//     if ($(event.target).closest('.tree-view-item').is(this)) {
//       var selectorValue = $(this).attr('data-selector');

//       try {
//         // Get classes of the selector
//         const element = doc.querySelector(selectorValue);
//         const classes = element ? Array.from(element.classList) : [];

//         console.log('Tree Selection', element, classes);
//         console.log('TREE VIEW ITEM SELECTED:', selectorValue, classes);

//         reManageTreeSession();
        
//         // Use the debounced function
//         debouncedSetManagerSession(element || null, classes);        window.tweaks.restore();
//       } catch (error) {
//         console.error('Error occurred during tree view item click:', error);
//         initTreeView();
//       }
//     }
//   });
// }

function detachTreeViewItemListener() {
  $('#tree-body').off('click', '.tree-view-item');
}

// Attach the listener when #tree-body is inserted into the DOM
// $(document).on('DOMNodeInserted', '#tree-body', function() {
//   attachTreeViewItemListener();
// });

// // CSS styles for empty elements
var emptyDivStyles = `
/* Hide X axis */
 #lc-main {
    overflow-x: hidden!important;
  }

/* Hide LC Interface */
#lc-interface {
  display: none!important;
}

/* General Empty Element Styles */
*:empty:not([class]):not([id]) {
  background-color: #f7f7f7;
  border: 1px dashed #ccc;
  min-height: 50px;
}

div:empty:not([class]):not([id]) {
  display: block;
  background: repeating-linear-gradient(-45deg, #fafafa, #eaeaea 5px, white 5px);
  border: 1px solid #ddd;
  text-align: center;
}

div:empty:not([class]):not([id]):before {
  display: block;
  content: "DIV";
  margin: 20px 0px;
  font-size: 14px;
  text-transform: uppercase;
  font-family: Arial, sans-serif;
  color: #666;
}

div:empty:not([class]):not([id]):hover {
  cursor: pointer;
}

.container:empty {
  display: block;
  background: repeating-linear-gradient(-45deg, #fafafa, #eaeaea 5px, white 5px);
  border: 1px solid #ddd;
  text-align: center;
}

.container:empty:before {
  display: block;
  content: "CONTAINER";
  margin: 20px 0px;
  font-size: 14px;
  text-transform: uppercase;
  font-family: Arial, sans-serif;
  color: #666;
}

.container:empty:hover {
  cursor: pointer;
}

a[href="#"]:empty:not([class]):not([id]) {
  display: inline-block;
  background: repeating-linear-gradient(-45deg, #fafafa, #eaeaea 5px, white 5px);
  border: 1px solid #ddd;
  text-decoration: none;
  text-align: center;
}

a[href="#"]:empty:not([class]):not([id]):before {
  display: inline-block;
  content: "LINK";
  margin: 5px;
  font-size: 14px;
  text-transform: uppercase;
  font-family: Arial, sans-serif;
  color: #666;
}

a[href="#"]:empty:not([class]):not([id]):hover {
  cursor: pointer;
}

/* Flex Empty Block Styles */
.flex:empty {
  display: flex;
  background: repeating-linear-gradient(-45deg, #fafafa, #eaeaea 5px, white 5px);
  border: 1px solid #ddd;
  text-align: center;
  justify-content: center;
  align-items: center;
}

.flex:empty:before {
  content: "FLEX ROW";
  font-size: 14px;
  text-transform: uppercase;
  font-family: Arial, sans-serif;
  color: #666;
}

.flex:empty:hover {
  cursor: pointer;
}

/* Flex Column Empty Block Styles */
.flex-col:empty {
  display: flex;
  flex-direction: column;
  background: repeating-linear-gradient(-45deg, #fafafa, #eaeaea 5px, white 5px);
  border: 1px solid #ddd;
  text-align: center;
  justify-content: center;
  align-items: center;
}

.flex-col:empty:before {
  content: "FLEX COL";
  font-size: 14px;
  text-transform: uppercase;
  font-family: Arial, sans-serif;
  color: #666;
}

.flex-col:empty:hover {
  cursor: pointer;
}
/* General Empty Element Styles */
*:empty:not([class]):not([id]) {
  background-color: #f7f7f7;
  border: 1px dashed #ccc;
  min-height: 50px;
  width: 100%;
  box-sizing: border-box;
  padding: 10px;
}

div:empty:not([class]):not([id]) {
  display: block;
  background: repeating-linear-gradient(-45deg, #fafafa, #eaeaea 5px, white 5px);
  border: 1px solid #ddd;
  text-align: center;
  width: 100%;
  box-sizing: border-box;
  padding: 10px;
}

div:empty:not([class]):not([id]):before {
  display: block;
  content: "DIV";
  margin: 20px 0px;
  font-size: 14px;
  text-transform: uppercase;
  font-family: Arial, sans-serif;
  color: #666;
}

div:empty:not([class]):not([id]):hover {
  cursor: pointer;
}

.container:empty {
  display: block;
  background: repeating-linear-gradient(-45deg, #fafafa, #eaeaea 5px, white 5px);
  border: 1px solid #ddd;
  text-align: center;
  width: 100%;
  box-sizing: border-box;
  padding: 10px;
}

.container:empty:before {
  display: block;
  content: "CONTAINER";
  margin: 20px 0px;
  font-size: 14px;
  text-transform: uppercase;
  font-family: Arial, sans-serif;
  color: #666;
}

.container:empty:hover {
  cursor: pointer;
}

a[href="#"]:empty:not([class]):not([id]) {
  display: inline-block;
  background: repeating-linear-gradient(-45deg, #fafafa, #eaeaea 5px, white 5px);
  border: 1px solid #ddd;
  text-decoration: none;
  text-align: center;
  width: 100%;
  box-sizing: border-box;
  padding: 10px;
}

a[href="#"]:empty:not([class]):not([id]):before {
  display: inline-block;
  content: "LINK";
  margin: 5px;
  font-size: 14px;
  text-transform: uppercase;
  font-family: Arial, sans-serif;
  color: #666;
}

a[href="#"]:empty:not([class]):not([id]):hover {
  cursor: pointer;
}

/* Flex Empty Block Styles */
.flex:empty {
  display: flex;
  background: repeating-linear-gradient(-45deg, #fafafa, #eaeaea 5px, white 5px);
  border: 1px solid #ddd;
  text-align: center;
  justify-content: center;
  align-items: center;
  width: 100%;
  box-sizing: border-box;
  padding: 10px;
}

.flex:empty:before {
  content: "FLEX ROW";
  font-size: 14px;
  text-transform: uppercase;
  font-family: Arial, sans-serif;
  color: #666;
}

.flex:empty:hover {
  cursor: pointer;
}

/* Flex Column Empty Block Styles */
.flex-col:empty {
  display: flex;
  flex-direction: column;
  background: repeating-linear-gradient(-45deg, #fafafa, #eaeaea 5px, white 5px);
  border: 1px solid #ddd;
  text-align: center;
  justify-content: center;
  align-items: center;
  width: 100%;
  box-sizing: border-box;
  padding: 10px;
}

.flex-col:empty:before {
  content: "FLEX COL";
  font-size: 14px;
  text-transform: uppercase;
  font-family: Arial, sans-serif;
  color: #666;
}

.flex-col:empty:hover {
  cursor: pointer;
}

/* Grid Empty Block Styles */
.grid:empty {
  display: grid;
  background: repeating-linear-gradient(-45deg, #fafafa, #eaeaea 5px, white 5px);
  border: 1px solid #ddd;
  text-align: center;
  justify-content: center;
  align-items: center;
  width: 100%;
  box-sizing: border-box;
  padding: 10px;
}

.grid:empty:before {
  content: "GRID";
  font-size: 14px;
  text-transform: uppercase;
  font-family: Arial, sans-serif;
  color: #666;
}

.grid:empty:hover {
  cursor: pointer;
}

`;

// Function to inject CSS styles into the iframe
function injectEmptyDivStyles() {
  var iframe = document.getElementById('previewiframe');
  var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
  var styleElement = iframeDoc.createElement('style');
  styleElement.textContent = emptyDivStyles;
  iframeDoc.head.appendChild(styleElement);
}

// Call the function to inject styles when the iframe is loaded
document.getElementById('previewiframe').addEventListener('load', injectEmptyDivStyles);


// Function to handle changes to the selector attribute
function handleSelectorChange(mutations) {
  mutations.forEach((mutation) => {
    if (mutation.type === 'attributes' && mutation.attributeName === 'selector') {
      const changedElement = mutation.target;
      
      const elementId = changedElement.id ? `ID: ${changedElement.id}` : 'No ID';
      const elementClasses = changedElement.classList.length > 0 ? `Classes: ${Array.from(changedElement.classList).join(', ')}` : 'No Classes';
      
      console.log(`Selector attribute changed:`);
      console.log(` Element: ${changedElement.tagName}`);
      console.log(` Item: ${changedElement.getAttribute('item-type')}`);
      console.log(` ${elementId}`);
      console.log(` ${elementClasses}`);
      console.log(` Selector: ${changedElement.getAttribute('selector')}`);
      
      // Get the iframe of the selector
      const iframe = document.querySelector('#previewiframe');
      const previewEl = iframe.contentDocument.querySelector(changedElement.getAttribute('selector'));
      
      // Get the classes of the selector
      const classes = Array.from(previewEl.classList);
      
      // Set manager session
      setManagerSession(previewEl, classes);
    }
  });
}

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', () => {
  // Create a MutationObserver instance
  const observer = new MutationObserver(handleSelectorChange);

  // Configure the observer options
  const observerOptions = {
    attributes: true, // Listen for attribute changes
    attributeFilter: ['selector'], // Only observe changes to the 'selector' attribute
    subtree: true // Observe changes in the entire subtree of #sidepanel
  };

  // Get the #sidepanel element
  const sidepanel = document.querySelector('#sidepanel');

  // Start observing the #sidepanel element with the specified options
  observer.observe(sidepanel, observerOptions);
});



function waitForIframeLoad() {
  const iframe = document.querySelector('#previewiframe');
  if (iframe) {
    iframe.addEventListener('load', function() {
      attachIframeClickListener(iframe);
      injectCSS(iframe);
      processTwigElements(iframe);
      checkQueries();
      monitorIframeChanges(iframe);
      updatePluginOptions();

      // Create event listeners and observer inside the child iframe
      createOverlayEventListeners(iframe);
      createActiveItemObserver(iframe);
      createActiveOverlayListener(iframe);
    });
  } else {
    // If the iframe is not found, wait for a short delay and check again
    setTimeout(waitForIframeLoad, 100);
  }
}


// when data-active-overlay is clicked get data-selector from data-active-overlay and click the element
function createActiveOverlayListener(iframe) {
  iframe.addEventListener('click', function(event) {
    const target = event.target;
    const activeOverlay = target.closest('[data-active-overlay]');
    if (activeOverlay) {
      console.log('Active Overlay Clicked:', activeOverlay)
      const selector = activeOverlay.getAttribute('data-selector');
      const element = document.querySelector(selector);
      if (element) {
        element.click();
      }
    }
  });
}

// Call the function to wait for the iframe to load
waitForIframeLoad();

// Function to create event listeners inside the child iframe
function createOverlayEventListeners(iframe) {
  const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

  // Function to create and position the overlay element
  function createOverlay(element, type) {
    const overlay = document.createElement('div');

    const rect = element.getBoundingClientRect();
    const scrollX = iframeDoc.documentElement.scrollLeft || iframeDoc.body.scrollLeft;
    const scrollY = iframeDoc.documentElement.scrollTop || iframeDoc.body.scrollTop;
    // add a class to the overlay
    overlay.classList.add('lc-overlay');
    overlay.style.position = 'absolute';
    overlay.style.left = rect.left + scrollX - 4 + 'px';
    overlay.style.top = rect.top + scrollY - 4 + 'px';
    overlay.style.width = rect.width + 8 + 'px';
    overlay.style.height = rect.height + 8 + 'px';
    overlay.style.border = '2px dashed';
    overlay.style.pointerEvents = 'none';
    overlay.style.zIndex = '1';
    overlay.style.borderRadius = '4px';
    // cursor crosshair
    overlay.style.cursor = 'crosshair';

    const tagElement = document.createElement('div');
    // add a class to the tagElement
    tagElement.classList.add('lc-tag');
    tagElement.textContent = element.tagName.toUpperCase();
    tagElement.style.position = 'absolute';
    tagElement.style.top = '-20px';
    tagElement.style.left = '0';
    tagElement.style.backgroundColor = 'rgba(232,62,139)';
    tagElement.style.color = '#fff';
    tagElement.style.padding = '2px 0.8rem';
    tagElement.style.fontSize = '12px';
    tagElement.style.fontWeight = 'bold';
    tagElement.style.borderRadius = '4px';
    tagElement.style.fontFamily = 'sans-serif';

    overlay.appendChild(tagElement);

    if (type === 'hover') {
      // overlay.style.backgroundColor = 'rgba(253, 230, 138, 0.1)'; // Pale Tailwind yellow background with transparency
      overlay.style.borderColor = 'rgba(232,62,139)'; // Pale Tailwind yellow border with transparency
    } else if (type === 'active') {
      // overlay.style.backgroundColor = 'rgba(167, 243, 208, 0.1)'; // Pale Tailwind green background with transparency
      overlay.style.borderColor = 'rgb(39,198,218)'; // Pale Tailwind green border with transparency
    }

    iframeDoc.body.appendChild(overlay);

    return overlay;
  }

  // Function to handle mouseover event
  function handleMouseOver(event) {
    const target = event.target;
    if (target.hasAttribute('data-hover-item')) {
      // Remove existing hover overlay
      const existingHoverOverlay = iframeDoc.querySelector('[data-hover-overlay]');
      if (existingHoverOverlay) {
        existingHoverOverlay.remove();
      }
      // Create new hover overlay
      createOverlay(target, 'hover').setAttribute('data-hover-overlay', '');
    }
  }

  // Function to handle mouseout event
  function handleMouseOut(event) {
    const target = event.target;
    if (target.hasAttribute('data-hover-item')) {
      // Remove hover overlay
      const hoverOverlay = iframeDoc.querySelector('[data-hover-overlay]');
      if (hoverOverlay) {
        hoverOverlay.remove();
      }
    }
  }

  // Add event listeners to the iframe's document
  iframeDoc.addEventListener('mouseover', handleMouseOver);
  iframeDoc.addEventListener('mouseout', handleMouseOut);

  // Update overlay positions on scroll within the iframe
  iframe.contentWindow.addEventListener('scroll', function() {
    const hoverOverlay = iframeDoc.querySelector('[data-hover-overlay]');
    if (hoverOverlay) {
      const target = hoverOverlay.parentNode.closest('[data-hover-item]');
      if (target) {
        const rect = target.getBoundingClientRect();
        const scrollX = iframeDoc.documentElement.scrollLeft || iframeDoc.body.scrollLeft;
        const scrollY = iframeDoc.documentElement.scrollTop || iframeDoc.body.scrollTop;

        hoverOverlay.style.left = rect.left + scrollX - 4 + 'px';
        hoverOverlay.style.top = rect.top + scrollY - 4 + 'px';
      }
    }
  });
}

// Function to create the active item observer inside the child iframe
function createActiveItemObserver(iframe) {
  const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

  // Function to create and position the overlay element
  function createOverlay(element, type) {
    const overlay = document.createElement('div');

    const rect = element.getBoundingClientRect();
    const scrollX = iframeDoc.documentElement.scrollLeft || iframeDoc.body.scrollLeft;
    const scrollY = iframeDoc.documentElement.scrollTop || iframeDoc.body.scrollTop;

    overlay.style.position = 'absolute';
    overlay.style.left = rect.left + scrollX - 4 + 'px';
    overlay.style.top = rect.top + scrollY - 4 + 'px';
    overlay.style.width = rect.width + 8 + 'px';
    overlay.style.height = rect.height + 8 + 'px';
    overlay.style.border = '2px dashed';
    overlay.style.pointerEvents = 'none';
    overlay.style.zIndex = '1';
    overlay.style.borderRadius = '4px';
    // cursor crosshair
    overlay.style.cursor = 'crosshair';

    const tagElement = document.createElement('div');
    tagElement.textContent = element.tagName.toUpperCase();
    tagElement.style.position = 'absolute';
    tagElement.style.top = '-20px';
    tagElement.style.left = '0';
    tagElement.style.backgroundColor = 'rgba(39,198,218)';
    tagElement.style.color = '#fff';
    tagElement.style.padding = '2px 0.8rem';
    tagElement.style.fontSize = '12px';
    tagElement.style.fontWeight = 'bold';
    tagElement.style.borderRadius = '4px';
    tagElement.style.fontFamily = 'sans-serif';

    overlay.appendChild(tagElement);

    if (type === 'active') {
      // overlay.style.backgroundColor = 'rgba(167, 243, 208, 0.1)'; // Pale Tailwind green background with transparency
      overlay.style.borderColor = 'rgb(39,198,218)'; // Pale Tailwind green border with transparency
    }

    iframeDoc.body.appendChild(overlay);

    return overlay;
  }

  // Function to handle attribute changes for selected items
  function handleAttributeChanges(mutationsList, observer) {
    for (let mutation of mutationsList) {
      if (mutation.type === 'attributes' && mutation.attributeName === 'data-active-item') {
        const target = mutation.target;

        // remove all data-active-overlay
        const activeOverlays = iframeDoc.querySelectorAll('[data-active-overlay]');
        activeOverlays.forEach(overlay => {
          overlay.remove();
        });

        // Get all data-active-item elements, and create an overlay for each
        const activeItems = iframeDoc.querySelectorAll('[data-active-item]');
        activeItems.forEach(item => {
          createOverlay(item, 'active').setAttribute('data-active-overlay', '');
        });
      }
    }
  }

  // Create a MutationObserver to observe changes in the data-active-item attribute
  const observer = new MutationObserver(handleAttributeChanges);

  // Configure the observer to watch for attribute changes in the entire document subtree
  const config = { attributes: true, subtree: true, attributeFilter: ['data-active-item'] };

  // Start observing the iframe's document
  observer.observe(iframeDoc, config);

  // Update active overlay positions on scroll within the iframe
  iframe.contentWindow.addEventListener('scroll', function() {
    const activeOverlays = iframeDoc.querySelectorAll('[data-active-overlay]');
    activeOverlays.forEach(overlay => {
      const target = overlay.parentNode.closest('[data-active-item]');
      if (target) {
        const rect = target.getBoundingClientRect();
        const scrollX = iframeDoc.documentElement.scrollLeft || iframeDoc.body.scrollLeft;
        const scrollY = iframeDoc.documentElement.scrollTop || iframeDoc.body.scrollTop;

        overlay.style.left = rect.left + scrollX - 4 + 'px';
        overlay.style.top = rect.top + scrollY - 4 + 'px';
      }
    });
  });
}


function extractKeysAndValues(obj, prefix = 'context') {
  let result = [];

  for (let key in obj) {
    if (obj.hasOwnProperty(key)) {
      const value = obj[key];
      let formattedKey = `${prefix}/${key}`;

      if (Array.isArray(value)) {
        // Handle arrays
        const arrayKey = formattedKey.split('/').join('.').replace('context.', '');
        const arrayValue = `{% for item in ${arrayKey} %}\n\n{% endfor %}`;
        result.push({
          caption: arrayKey,
          value: arrayValue,
          meta: 'Context',
          score: 100
        });
        
        // Generate autocomplete suggestions for array items
        const arrayItemKeys = extractKeysAndValues(value[0], formattedKey);
        arrayItemKeys.forEach(item => {
          const itemKey = item.caption.replace(/\/\d+/g, '');
          const itemValue = `{{ item${itemKey.replace(formattedKey, '').split('/').join('.')} }}`;
          result.push({
            caption: `${arrayKey}.${itemKey.split('/').pop()}`,
            value: itemValue,
            meta: 'Context',
            score: 90
          });
        });
      } else if (typeof value === 'object' && value !== null) {
        // Handle nested objects
        result = result.concat(extractKeysAndValues(value, formattedKey));
      } else {
        // Handle primitive values
        const formattedValue = `{{ ${formattedKey.replace('context/', '').split('/').join('.')} }}`;
        result.push({
          caption: formattedKey.split('/').join('.').replace('context.', ''),
          value: formattedValue,
          meta: 'Context',
          score: 100
        });
      }
    }
  }

  return result;
}

// Select the target node that you want to observe
const targetNode = document.documentElement; // Observing the entire document

// Create an observer instance
const hiddenAttributeObserver = new MutationObserver(function(mutationsList, observer) {
  for (let mutation of mutationsList) {
    if (mutation.type === 'attributes' && mutation.attributeName === 'hidden') {
      const element = mutation.target;
      if (element.classList.contains('tree-children')) {
        element.removeAttribute('hidden');
      }
    }
  }
});

// Configuration options for the observer
const config = { attributes: true, subtree: true };

hiddenAttributeObserver.observe(targetNode, config);

// Get the element with the ID "lc-html-editor-window"
const lcHtmlEditorWindow = document.getElementById('lc-html-editor-window');

// Create a MutationObserver instance with a more unique and agnostic name
const agnosticSelectorChangeObserver = new MutationObserver(function(mutations) {
 mutations.forEach(function(mutation) {
   if (mutation.type === 'attributes' && mutation.attributeName === 'selector') {
     const newSelector = lcHtmlEditorWindow.getAttribute('selector');
     // Get the element 
      const element = doc.querySelector(newSelector);
      // Get the classes of the selector
      const classes = Array.from(element.classList) || [];

      setManagerSession(element, classes);
   }
 });
});

// Configure the observer to watch for attribute changes
const agnosticSelectorChangeConfig = {
 attributes: true,
 attributeFilter: ['selector']
};

// Start observing the lcHtmlEditorWindow element
agnosticSelectorChangeObserver.observe(lcHtmlEditorWindow, agnosticSelectorChangeConfig);


document.addEventListener('DOMContentLoaded', function() {
  const previewIframe = document.getElementById('previewiframe');

  previewIframe.addEventListener('mouseenter', function() {
    console.log('Mouse in preview');
    // when mouse inside preview .lc-overlay in iframe should be opacity: 1
    const iframe = document.getElementById('previewiframe');
    const iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
    const overlays = iframeDocument.querySelectorAll('.lc-overlay');
    overlays.forEach(overlay => {
      overlay.style.opacity = '1';
    });
  });

  previewIframe.addEventListener('mouseleave', function() {
    console.log('Mouse outside preview');

    // when mouse outside preview .lc-overlay in iframe should be opacity: 0
    const iframe = document.getElementById('previewiframe');
    const iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
    const overlays = iframeDocument.querySelectorAll('.lc-overlay');
    overlays.forEach(overlay => {
      overlay.style.opacity = '0';
    });
  });
});