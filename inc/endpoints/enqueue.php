<?php 

function agnostic_enqueue_active_fonts() {
    $designations = get_option('my_theme_font_designations', array());
    $fonts_dir = get_template_directory() . '/inc/fonts';
    $fonts_url = get_template_directory_uri() . '/inc/fonts';

    if (!empty($designations)) {
        echo "<style id='ag-fonts'>\n";
        foreach ($designations as $designation => $fonts) {
            foreach ($fonts as $font_name) {
                $font_dir = $fonts_dir . '/' . $font_name;
                $font_url = $fonts_url . '/' . $font_name;

                if (is_dir($font_dir)) {
                    $font_files = glob($font_dir . '/*.{woff2,woff,ttf}', GLOB_BRACE);
                    $weights_styles = array();

                    foreach ($font_files as $file) {
                        $filename = basename($file);
                        preg_match('/-([\w]+)\.(woff2|woff|ttf)$/', $filename, $matches);
                        
                        if (!empty($matches)) {
                            $variant = strtolower($matches[1]);
                            $ext = $matches[2];
                            
                            $weight = 'normal';
                            $style = 'normal';
                            
                            if (strpos($variant, 'italic') !== false) {
                                $style = 'italic';
                                $variant = str_replace('italic', '', $variant);
                            }
                            
                            switch ($variant) {
                                case 'thin': $weight = '100'; break;
                                case 'extralight': $weight = '200'; break;
                                case 'light': $weight = '300'; break;
                                case 'regular': $weight = '400'; break;
                                case 'medium': $weight = '500'; break;
                                case 'semibold': $weight = '600'; break;
                                case 'bold': $weight = '700'; break;
                                case 'extrabold': $weight = '800'; break;
                                case 'black': $weight = '900'; break;
                                default: 
                                    if (is_numeric($variant)) {
                                        $weight = $variant;
                                    }
                            }
                            
                            $key = $weight . $style;
                            if (!isset($weights_styles[$key])) {
                                $weights_styles[$key] = array(
                                    'weight' => $weight,
                                    'style' => $style,
                                    'files' => array()
                                );
                            }
                            $weights_styles[$key]['files'][$ext] = $filename;
                        }
                    }

                    foreach ($weights_styles as $data) {
                        echo "@font-face {\n";
                        echo "  font-family: '" . esc_attr($font_name) . "';\n";
                        echo "  font-style: " . esc_attr($data['style']) . ";\n";
                        echo "  font-weight: " . esc_attr($data['weight']) . ";\n";
                        echo "  font-display: swap;\n";
                        echo "  src: ";

                        $src_parts = array();
                        if (isset($data['files']['woff2'])) {
                            $src_parts[] = "url('" . esc_url($font_url . '/' . $data['files']['woff2']) . "') format('woff2')";
                        }
                        if (isset($data['files']['woff'])) {
                            $src_parts[] = "url('" . esc_url($font_url . '/' . $data['files']['woff']) . "') format('woff')";
                        }
                        if (isset($data['files']['ttf'])) {
                            $src_parts[] = "url('" . esc_url($font_url . '/' . $data['files']['ttf']) . "') format('truetype')";
                        }

                        echo implode(",\n       ", $src_parts) . ";\n";
                        echo "}\n\n";
                    }
                }
            }
        }
echo "</style>\n";
    }
}

add_action('wp_head', 'agnostic_enqueue_active_fonts', 100);
add_action('wp_builder_head', 'agnostic_enqueue_active_fonts', 100);