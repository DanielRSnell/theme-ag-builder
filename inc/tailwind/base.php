<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Updated CSS with Dynamic Config and Plugins</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <script type="module">
      // Make defaultTheme and colors available globally
      window.defaultTheme = tailwind.defaultTheme;
      window.colors = tailwind.colors;

      // Dynamic import function with logging
      async function dynamicImport(module) {
        try {
          const imported = await import(`https://cdn.skypack.dev/${module}`);
          console.log(`Successfully resolved plugin: ${module}`);
          return imported.default;
        } catch (error) {
          console.error(`Failed to resolve plugin: ${module}`, error);
          return null;
        }
      }

      // Function to transform require calls to dynamic imports
      function transformConfig(configString) {
        return configString.replace(
          /require\(['"](.*?)['"]\)/g,
          (match, p1) => `await dynamicImport('${p1}')`
        );
      }

      // Get and transform the user config
      const userConfigString = `<?php echo get_user_tailwind_config_string(); ?>`;
      const transformedConfigString = transformConfig(userConfigString);

      // Create a new function to evaluate the transformed config
      const configFunction = new Function('colors', 'defaultTheme', 'dynamicImport', `
        return (async () => {
          const module = {};
          ${transformedConfigString}
          return module.exports;
        })();
      `);

      // Function to save Tailwind CSS via AJAX
      async function saveTailwindCSS(css) {
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
            console.log('Tailwind CSS saved successfully');
            if (window.parent) {
              window.parent.console.log('Tailwind Compiler Ran Successfully');
              window.parent.postMessage({ type: 'tailwindCSSSaved' }, '*');
            }
          } else {
            console.error('Failed to save Tailwind CSS:', result.data);
          }
        } catch (error) {
          console.error('Error saving Tailwind CSS:', error);
        }
      }

      // Function to check, tag, and save Tailwind CSS
      function checkAndTagTailwindStyle() {
        const styleTags = document.head.getElementsByTagName('style');
        for (let i = 0; i < styleTags.length; i++) {
          const styleTag = styleTags[i];
          const isTailwind = styleTag.textContent.includes('* ! tailwindcss v3.4.4 | MIT License | https://tailwindcss.com *');

          if (isTailwind) {
            if (styleTag.id !== 'agnostic-tailwind') {
              styleTag.id = 'agnostic-tailwind';
              console.log('Tailwind CSS style tag has been tagged with id "agnostic-tailwind"');
            }
            saveTailwindCSS(styleTag.textContent);
            return true;
          }
        }
        return false;
      }

      // Function to continuously check for the Tailwind CSS style tag
      function continuouslyCheckForTailwindStyle() {
        if (!checkAndTagTailwindStyle()) {
          setTimeout(continuouslyCheckForTailwindStyle, 100);
        } else {
          // If found, set up an observer to watch for changes
          const styleTag = document.getElementById('agnostic-tailwind');
          if (styleTag) {
            const observer = new MutationObserver((mutations) => {
              mutations.forEach((mutation) => {
                if (mutation.type === 'childList' || mutation.type === 'characterData') {
                  saveTailwindCSS(styleTag.textContent);
                }
              });
            });
            observer.observe(styleTag, { childList: true, characterData: true, subtree: true });
          }
          // Continue checking periodically in case the style tag is replaced
          setTimeout(continuouslyCheckForTailwindStyle, 1000);
        }
      }

      // Execute the config function and start checking for Tailwind styles
      try {
        console.log('Starting to resolve Tailwind configuration and plugins...');
        const config = await configFunction(colors, defaultTheme, dynamicImport);
        tailwind.config = config;
        console.log('Tailwind config successfully set:', tailwind.config);

        // Start the continuous checking process
        continuouslyCheckForTailwindStyle();

      } catch (error) {
        console.error('Error in Tailwind configuration:', error);
      }
    </script>
</head>
<body>
    <?php
function get_tailwind_bundle_markup()
{
    $output = '';

    // Pull templates from various sources
    $templates = array();

    // Pages
    $pages = get_pages();
    foreach ($pages as $page) {
        $templates[] = array('type' => 'page', 'content' => $page->post_content);
    }

    // Custom post types: lc_block, lc_section, lc_partial, lc_dynamic_sections
    $custom_post_types = array('lc_block', 'lc_section', 'lc_partial', 'lc_dynamic_sections');
    foreach ($custom_post_types as $post_type) {
        $posts = get_posts(array('post_type' => $post_type, 'numberposts' => -1));
        foreach ($posts as $post) {
            $templates[] = array('type' => $post_type, 'content' => $post->post_content);
        }
    }

    // Output all templates
    foreach ($templates as $template) {
        $output .= '<div class="template-' . esc_attr($template['type']) . '">';
        $output .= wp_kses_post($template['content']);
        $output .= '</div>';
    }

    return $output;
}

// Output the markup
echo get_tailwind_bundle_markup();
?>

    <script>
    function toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
    }
    </script>
    <button onclick="toggleDarkMode()">Toggle Dark Mode</button>
</body>
</html>