<?php
/**
 * Plugin Name: VINTTRO Custom Modal Enhancements
 * Description: Core helper scripts to manage viewport configurations, CF7 conditional layouts, and multi-step modal routing.
 * Version: 2.1
 * Author: VINTTRO Dev
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Define the function to output the meta tag
function my_custom_viewport_meta_tag() {
    // Prevent unprompted mobile zooming while allowing standard responsive resizing
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=6.0">';
}

// 2. Hook the function into the <head> section of your site
add_action( 'wp_head', 'my_custom_viewport_meta_tag' );

/**
 * Retrieves the numerical ID of a Contact Form 7 form whose title contains a string.
 *
 * @param string $title_substring The substring the CF7 form title must contain (e.g., 'quote-form').
 * @return int|null The form ID on success, or null if not found.
 */
function get_cf7_id_by_title_contains( $title_substring ) {
    $args = array(
        'post_type'      => 'wpcf7_contact_form', 
        'post_status'    => 'publish',
        's'              => $title_substring,     
        'posts_per_page' => 1,                    
        'fields'         => 'ids',                
    );

    $forms = get_posts( $args );

    if ( ! empty( $forms ) ) {
        return (int) $forms[0]; 
    }

    return null;
}

/**
 * Output JavaScript event scripts and Custom Stylesheets inside wp_footer.
 * This guarantees scripts execute cleanly after both CF7 and Popup Builder load.
 */
function my_custom_cf7_scripts() {
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // --- CONDITIONAL FIELD TRIGGERS ---
            const conditionalGroups = document.querySelectorAll('.conditional-field');

            conditionalGroups.forEach(group => {
                const targetName = group.getAttribute('data-depends-on');
                const targetValue = group.getAttribute('data-if-value');

                const toggleField = () => {
                    const checkedRadio = document.querySelector(`input[name="${targetName}"]:checked`);
                    const currentValue = checkedRadio ? checkedRadio.value : null;

                    if (currentValue === targetValue) {
                        group.style.display = 'block';
                    } else {
                        group.style.display = 'none';
                        group.querySelectorAll('input, textarea, select').forEach(el => el.value = '');
                    }
                };

                const allRadios = document.querySelectorAll(`input[name="${targetName}"]`);
                
                if (allRadios.length > 0) {
                    allRadios.forEach(radio => {
                        radio.addEventListener('change', toggleField);
                    });
                    toggleField();
                }
            });
        });

        /**
         * VINTTRO State Management Helper
         * Resets the multi-step view back to Step 1 (The Category Buttons screen)
         */
        function resetVinttroFormState() {
            const selectorScreen = document.getElementById('insurance-selector');
            if (selectorScreen) {
                selectorScreen.style.display = 'block';
            }
            
            // Hide all individual contact form wrapper blocks
            document.querySelectorAll('.hidden-insurance-form').forEach(form => {
                form.style.display = 'none';
            });
        }

        // Bind standard DOM event hooks dispatched by the Popup Builder engine
        document.addEventListener('sgpbWillOpen', resetVinttroFormState);
        window.addEventListener('sgpbWillOpen', resetVinttroFormState);
        document.addEventListener('sgpbDidClose', resetVinttroFormState);

        // Fallback jQuery bindings in case Popup Builder triggers its hooks on the jQuery namespace
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('sgpbWillOpen', function() {
                resetVinttroFormState();
            });
            jQuery(document).on('sgpbDidClose', function() {
                resetVinttroFormState();
            });
        }

        /**
         * Safely scoped globally so popup HTML execution blocks can target it via onclick actions
         */
        window.openInsuranceForm = function(type) {
            const selectorScreen = document.getElementById('insurance-selector');
            if (selectorScreen) {
                selectorScreen.style.display = 'none';
            }
            
            const selectedForm = document.getElementById('form-container-' + type);
            if (selectedForm) {
                selectedForm.style.display = 'block';
            }
            
            // Allow dynamic CF7 layout structures to settle before recalculating popup modal heights
            setTimeout(function() {
                window.dispatchEvent(new Event('resize'));
            }, 50);
        };
    </script>
    
    <style type="text/css">
        /* --- Popup Selector Typography & Structure --- */
        .vinttro-selector-heading {
            text-align: center;
            /* text-transform: uppercase; -> Commented out to match normal site title casing */
            letter-spacing: 0.05em;
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 35px !important;
            color: #916D1C;
        }

        /* Centered Grid Matrix Sizing Structure */
        .insurance-grid {
            display: grid !important;
            grid-template-columns: repeat(3, 1fr) !important; /* Forces 3 symmetrical columns on desktop */
            grid-auto-rows: 1fr !important;                   /* Forces all buttons to be the exact same height */
            gap: 20px !important;
            padding: 10px 0 !important;
            justify-content: center !important;               /* Centers grid items horizontally */
            margin: 0 auto !important;
            max-width: 820px !important;                      /* Constrains max width for premium layout proportions */
            width: 100% !important;
            box-sizing: border-box !important;
        }

        /* Prevent rogue <br> tags from acting as grid cells (Critical wpautop bypass) */
        .insurance-grid br {
            display: none !important;
        }

        /* --- Option 3 Minimalist Button Aesthetics --- */
        .insure-btn {
            background: #ffffff !important;
            border: 1px solid #d4af37 !important;   /* Premium VINTTRO Gold accent */
            border-radius: 4px !important;          /* Elegant, soft-rounded corners */
            padding: 22px 15px !important;
            color: #d4af37 !important;              /* Editorial Gold heading color */
            font-size: 15px !important;              /* Matches typical H1/H2 header scales */
            font-weight: 500 !important;
            font-family: Georgia, serif !important; /* Editorial serif selection */
            cursor: pointer;
            box-shadow: none !important;
            display: flex !important;
            align-items: center;
            justify-content: center;
            box-sizing: border-box !important;
            margin: 0 !important;
            width: 100% !important;
            transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1) !important;
        }

        .insure-btn:hover {
            background: #f5f5f5 !important;         /* Soft, light grey background on hover */
            border-color: #d4af37 !important;       /* Retains the distinctive gold frame outline */
            color: #111111 !important;              /* Charcoal black text for perfect readability and contrast */
            transform: translateY(-2px);
        }

        /* --- Dynamic Styling For Hidden Input Forms --- */
        .form-padding-wrapper {
            padding: 30px 25px !important;
            box-sizing: border-box;
        }
        .hidden-insurance-form label {
            display: block !important;
            margin-bottom: 20px !important;
            width: 100% !important;
            font-weight: 500;
        }
        .hidden-insurance-form input[type="text"],
        .hidden-insurance-form input[type="email"],
        .hidden-insurance-form input[type="tel"],
        .hidden-insurance-form input[type="date"],
        .hidden-insurance-form select,
        .hidden-insurance-form textarea {
            width: 100% !important;
            margin-top: 8px !important;
            padding: 12px !important;
            box-sizing: border-box !important;
        }
        .hidden-insurance-form hr {
            margin: 25px 0 !important;
            border: 0;
            border-top: 1px solid #eee;
        }

        /* Tablet Layout adjustments */
        @media (max-width: 800px) {
            .insurance-grid {
                grid-template-columns: repeat(2, 1fr) !important; /* Clean 2-column grid on tablets */
            }
        }

        /* Mobile Adaptive Layout Adjustments */
        @media (max-width: 580px) {
            .insurance-grid {
                grid-template-columns: 1fr !important; /* Stack button cards into full-width tap rows on mobile */
                gap: 15px !important;
                max-width: 320px !important;           /* Elegant vertical column boundary constraint */
                margin: 0 auto !important;
            }
            .insure-btn {
                padding: 18px 15px !important;
            }
        }
    </style>
    <?php
}
add_action('wp_footer', 'my_custom_cf7_scripts');