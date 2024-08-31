import TailwindcssAutocomplete from 'https://esm.sh/@yabe-siul/tailwindcss-autocomplete/dist/index.js';

let autocomplete;
const targetWindow = window.parent || window;

function initializeAutocomplete(config) {
    if (config) {
        autocomplete = new TailwindcssAutocomplete(config);
        console.log('Tailwind Autocomplete initialized with config:', config);
        targetWindow.autocomplete = autocomplete;
    }
}

function updateAutocompleteConfig(config) {
    if (config && autocomplete) {
        autocomplete.config = config;
        console.log('Tailwind Autocomplete config updated:', config);
    }
}

targetWindow.getSuggestionList = async function(value) {
    if (!autocomplete) {
        console.error('Autocomplete is not initialized.');
        return [];
    }
    console.time('Fetching suggestions');
    try {
        const list = await autocomplete.getSuggestionList(value);
        console.timeEnd('Fetching suggestions');

        return list.map(item => {
            return item;
        });
    } catch (error) {
        console.error('Error fetching suggestions:', error);
        return [];
    }
};

// Global function to update the config
targetWindow.tailwindUpdateConfig = function() {
    const newConfig = window.tailwind?.config;
    if (!autocomplete) {
        initializeAutocomplete(newConfig);
    } else {
        updateAutocompleteConfig(newConfig);
    }
};

// Set up a custom setter for targetWindow.tailwind.config
let tailwindConfig = targetWindow.tailwind?.config;
Object.defineProperty(targetWindow, 'tailwind', {
    get() {
        return { config: tailwindConfig };
    },
    set(newValue) {
        if (newValue && newValue.config !== tailwindConfig) {
            tailwindConfig = newValue.config;
            targetWindow.tailwindUpdateConfig(tailwindConfig);
        }
    }
});

// Initialize if config is already available
if (tailwindConfig) {
    initializeAutocomplete(tailwindConfig);
} else {
    // Check periodically for config
    const checkConfigInterval = setInterval(() => {
        if (targetWindow.tailwind?.config) {
            tailwindConfig = targetWindow.tailwind.config;
            initializeAutocomplete(tailwindConfig);
            clearInterval(checkConfigInterval);
        }
    }, 1000);
}


(function(targetWindow) {
    const moduleClassSorter = {
        sort(classes) {
            let tailwindConfig = targetWindow.tailwind?.config;
            const twContext = targetWindow.twContext; // Assuming twContext is globally available

            const parts = classes
                .split(/\s+/)
                .filter((x) => x !== "" && x !== "|");

            // Check if twContext and getClassOrder are available
            if (!twContext || typeof twContext.getClassOrder !== 'function') {
                console.warn('twContext or getClassOrder is not available. Using fallback sorting.');
                return this.fallbackSort(parts);
            }

            try {
                const knownClassNamesWithOrder = twContext.getClassOrder(parts);

                const sortedClassNames = knownClassNamesWithOrder
                    .sort(([, a], [, z]) => {
                        if (a === z) return 0;
                        if (a === null) return -1;
                        if (z === null) return 1;
                        return this.bigSign(a - z);
                    })
                    .map(([className]) => className);

                return sortedClassNames.join(" ");
            } catch (error) {
                console.error('Error during class sorting:', error);
                return this.fallbackSort(parts);
            }
        },

        fallbackSort(classes) {
            // Simple alphabetical sort as a fallback
            return classes.sort().join(" ");
        },

        bigSign(bigIntValue) {
            return (bigIntValue > 0n) - (bigIntValue < 0n);
        },

        prefixCandidate(context, selector) {
            const prefix = context?.tailwindConfig?.prefix;
            return typeof prefix === "function" ? prefix(selector) : (prefix || '') + selector;
        },

        getClassOrderPolyfill(classes, context) {
            if (!context || !targetWindow.generateRules) {
                console.warn('Context or generateRules not available for polyfill. Using fallback sorting.');
                return classes.map((cls, index) => [cls, BigInt(index)]);
            }

            const parasiteUtilities = new Set([
                this.prefixCandidate(context, "group"),
                this.prefixCandidate(context, "peer"),
            ]);

            const classNamesWithOrder = [];

            for (const className of classes) {
                let order;
                try {
                    order = targetWindow.generateRules.generateRules(new Set([className]), context)
                        .sort(([a], [z]) => this.bigSign(z - a))[0]?.[0] ?? null;
                } catch (error) {
                    console.warn(`Error generating rules for "${className}":`, error);
                    order = null;
                }

                if (order === null && parasiteUtilities.has(className)) {
                    order = context.layerOrder?.components ?? null;
                }

                classNamesWithOrder.push([className, order]);
            }

            return classNamesWithOrder;
        },
    };

    // Make the moduleClassSorter available globally
    targetWindow.twClass = moduleClassSorter;
    window.parent.twClasses = moduleClassSorter;

})(typeof window !== 'undefined' ? window : global);