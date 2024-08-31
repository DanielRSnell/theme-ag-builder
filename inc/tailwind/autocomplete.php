<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tailwind CSS with Dynamic Config and Plugins</title>
    <script src="https://cdn.tailwindcss.com"></script>

<script type="module">
window.defaultTheme = tailwind.defaultTheme;
window.colors = tailwind.colors;

async function dynamicImport(module) {
  try {
    const imported = await import(`https://cdn.skypack.dev/${module}`);
    console.log(`TAILWIND: Successfully resolved plugin: ${module}`);
    return imported.default;
  } catch (error) {
    console.error(`TAILWIND: Failed to resolve plugin: ${module}`, error);
    return null;
  }
}

function transformConfig(configString) {
  return configString.replace(
    /require\(['"](.*?)['"]\)/g,
    (match, p1) => `await dynamicImport('${p1}')`
  );
}

let lastSavedCSS = '';
let saveTimeout = null;
let isSaving = false;

function debounce(func, wait) {
  return function(...args) {
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(() => {
      func.apply(this, args);
    }, wait);
  };
}

async function saveTailwindCSS(css) {
  if (css === lastSavedCSS || isSaving) {
    return;
  }

  isSaving = true;
  console.log('TAILWIND: Saving Tailwind CSS...');
  const data = new FormData();
  data.append('action', 'save_tailwind_css');
  data.append('nonce', '<?php echo wp_create_nonce('save_tailwind_css_nonce'); ?>');
  data.append('css', css);

  try {
    const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
      method: 'POST',
      credentials: 'same-origin',
      body: data
    });
    const result = await response.json();
    if (result.success) {
      console.log('TAILWIND: CSS saved successfully');
      lastSavedCSS = css;
      window.parent.postMessage({ type: 'tailwindCSSSaved' }, '*');
    } else {
      console.error('TAILWIND: Failed to save CSS:', result.data);
    }
  } catch (error) {
    console.error('TAILWIND: Error saving CSS:', error);
  } finally {
    isSaving = false;
  }
}

const debouncedSaveTailwindCSS = debounce(saveTailwindCSS, 1000);

function isTailwindStyleTag(node) {
  return node.textContent.includes('tailwindcss v3.4.5 | MIT License');
}

function setupStyleObservation() {
  console.log('TAILWIND: Setting up style observation...');
  let tailwindStyleTag = null;

  const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      if (mutation.type === 'childList') {
        const addedNodes = Array.from(mutation.addedNodes);
        tailwindStyleTag = addedNodes.find(isTailwindStyleTag);
      } else if (mutation.type === 'characterData' &&
                 mutation.target.nodeName === '#text' &&
                 isTailwindStyleTag(mutation.target.parentNode)) {
        tailwindStyleTag = mutation.target.parentNode;
      }

      if (tailwindStyleTag) {
        debouncedSaveTailwindCSS(tailwindStyleTag.textContent);
        break;
      }
    }
  });

  observer.observe(document.head, {
    childList: true,
    characterData: true,
    subtree: true
  });

  console.log('TAILWIND: Started observing head for style changes');

  tailwindStyleTag = Array.from(document.head.getElementsByTagName('style')).find(isTailwindStyleTag);
  if (tailwindStyleTag) {
    console.log('TAILWIND: Initial style tag found, saving content');
    debouncedSaveTailwindCSS(tailwindStyleTag.textContent);
  } else {
    console.log('TAILWIND: No initial style tag found');
  }
}

const userConfigString = `<?php echo get_user_tailwind_config_string(); ?>`;
console.log('TAILWIND: User config string:', userConfigString);
const transformedConfigString = transformConfig(userConfigString);
console.log('TAILWIND: Transformed config string:', transformedConfigString);

const configFunction = new Function('colors', 'defaultTheme', 'dynamicImport', `
  return (async () => {
    const module = {};
    ${transformedConfigString}
    return module.exports;
  })();
`);

(async function() {
  try {
    console.log('TAILWIND: Starting to resolve configuration and plugins...');
    const config = await configFunction(colors, defaultTheme, dynamicImport);
    tailwind.config = config;
    console.log('TAILWIND: Config successfully set:', tailwind.config);

    setupStyleObservation();

  } catch (error) {
    console.error('TAILWIND: Error in configuration:', error);
  }
})();
</script>

<style type="text/tailwindcss">
  <?php
ag_app_css();
?>
</style>

</head>
<body>
    <?php
function get_tailwind_bundle_markup()
{
    $all_content = '';

    // Pull templates from various sources
    $templates = array();

    // Pages
    $pages = get_pages();
    foreach ($pages as $page) {
        $templates[] = array('type' => 'page', 'content' => $page->post_content, 'id' => $page->ID, 'title' => $page->post_title);
    }

    // Custom post types: lc_block, lc_section, lc_partial, lc_dynamic_sections, agnostic_component
    $custom_post_types = array('agnostic_view');
    // Create a filter for the custom post types
    $custom_post_types = apply_filters('tailwind_bundle_custom_post_types', $custom_post_types);

    foreach ($custom_post_types as $post_type) {
        $posts = get_posts(array('post_type' => $post_type, 'numberposts' => -1));
        foreach ($posts as $post) {
            $templates[] = array(
                'type' => $post_type,
                'content' => $post->post_content,
                'id' => $post->ID,
                'title' => $post->post_title,
                'slug' => $post->post_name,
            );
        }
    }

    // Combine all templates
    foreach ($templates as $template) {
        if ($template['type'] === 'agnostic_component') {
            // For agnostic_component, include the content and add data attributes
            $all_content .= sprintf(
                '<div class="template-%1$s" data-block-id="%2$s" data-block-slug="%3$s">%4$s</div>',
                esc_attr($template['type']),
                esc_attr($template['id']),
                esc_attr($template['slug']),
                $template['content']
            );
        } else {
            // For other types, keep the content hidden
            $all_content .= sprintf(
                '<div class=" template-%1$s" data-id="%2$s">%3$s</div>',
                esc_attr($template['type']),
                esc_attr($template['id']),
                $template['content']
            );
        }
    }

    // Create a filter for the all content
    $all_content = apply_filters('tailwind_bundle_all_content', $all_content);

    // Process all content at once
    $output = do_shortcode($all_content);

    // Check if Timber is available before using it
    // if (class_exists('Timber')) {
    //     $output = Timber::compile_string($output, array());
    // }

    return $output;
}

// Output the markup
echo get_tailwind_bundle_markup();
?>


        <?php echo_tailwind_autocomplete_scripts();?>

</body>
</html>