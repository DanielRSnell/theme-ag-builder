<?php

// add an endpoint to get all components or component by name / id for component fetching

class AgnosticViewsAPI
{
    private $dataFetcher;
    private $dataMerger;
    private $localDataProvider;

    public function __construct(
        DataFetcherInterface $dataFetcher,
        DataMergerInterface $dataMerger,
        LocalDataProviderInterface $localDataProvider
    ) {
        $this->dataFetcher = $dataFetcher;
        $this->dataMerger = $dataMerger;
        $this->localDataProvider = $localDataProvider;
    }

    public function registerRoutes()
    {
        add_action('rest_api_init', function () {
            $routes = [
                ['route' => '/views/grouped', 'methods' => 'GET', 'callback' => [$this, 'getGroupedAgnosticViews']],
                ['route' => '/views/grouped/(?P<category>[a-zA-Z0-9-]+)', 'methods' => 'GET', 'callback' => [$this, 'getComponentTypesForCategory']],
                ['route' => '/views/options/category', 'methods' => 'GET', 'callback' => [$this, 'getComponentCategories']],
                ['route' => '/views/options/types', 'methods' => 'GET', 'callback' => [$this, 'getComponentTypes']],
            ];

            foreach ($routes as $route) {
                register_rest_route('agnostic/v1', $route['route'], [
                    'methods' => $route['methods'],
                    'callback' => $route['callback'],
                    'permission_callback' => '__return_true',
                ]);
            }

            register_rest_route('agnostic/v1', '/views/grouped/(?P<category>[a-zA-Z0-9-]+)/(?P<type>[a-zA-Z0-9-]+)', [
                'methods' => 'GET',
                'callback' => [$this, 'getComponentsForCategoryAndType'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public function getGroupedAgnosticViews()
    {
        $localViews = $this->localDataProvider->getGroupedViews();
        $remoteViews = $this->dataFetcher->fetch('/views/grouped');
        $mergedViews = $this->dataMerger->merge($localViews, $remoteViews);
        return new WP_REST_Response(apply_filters('agnostic_grouped_views', $mergedViews), 200);
    }

    public function getComponentTypesForCategory($request)
    {
        $category = $request['category'];
        $localTypes = $this->localDataProvider->getComponentTypesForCategory($category);

        // Fetch all grouped views
        $allRemoteViews = $this->dataFetcher->fetch("/views/grouped");

        // Extract types for the specific category
        $remoteTypes = $allRemoteViews[$category] ?? [];

        $mergedTypes = $this->dataMerger->mergeTypes($localTypes, $remoteTypes);
        return new WP_REST_Response(apply_filters('agnostic_component_types_for_category', $mergedTypes, $category), 200);
    }

    public function getComponentsForCategoryAndType($request)
    {
        $category = $request['category'];
        $type = $request['type'];

        $local_components = $this->getLocalComponents($category, $type);
        $remote_components = $this->getRemoteComponents($category, $type);
        $components = array_merge($local_components, $remote_components);
        $components = apply_filters('agnostic_components_for_category_and_type', $components, $category, $type);

        $context = [
            'category' => $category,
            'type' => $type,
            'components' => $components,
        ];

        $html = $this->renderComponentsHtml($context);

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit();
    }

    private function renderComponentsHtml($context)
    {
        return Timber::compile('_components.twig', $context);
    }

    private function getLocalComponents($category, $type)
    {
        return $this->localDataProvider->getComponentsForCategoryAndType($category, $type);
    }

    private function getRemoteComponents($category, $type)
    {
        $remote_components = $this->dataFetcher->fetch("/views/grouped/{$category}/{$type}");
        foreach ($remote_components as &$component) {
            $component['source'] = 'remote';
        }
        return $remote_components;
    }

    public function getComponentCategories()
    {
        $localCategories = $this->localDataProvider->getComponentCategories();
        $remoteCategories = $this->dataFetcher->fetch('/views/options/category');
        $mergedCategories = array_merge($localCategories, $remoteCategories);
        return new WP_REST_Response(apply_filters('agnostic_component_categories', $mergedCategories), 200);
    }

    public function getComponentTypes()
    {
        $localTypes = $this->localDataProvider->getComponentTypes();
        $remoteTypes = $this->dataFetcher->fetch('/views/options/types');
        $mergedTypes = array_merge($localTypes, $remoteTypes);
        return new WP_REST_Response(apply_filters('agnostic_component_types', $mergedTypes), 200);
    }
}

interface DataFetcherInterface
{
    public function fetch($endpoint);
}

class RemoteDataFetcher implements DataFetcherInterface
{
    private $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function fetch($endpoint)
    {
        $response = wp_remote_get($this->baseUrl . $endpoint);
        if (is_wp_error($response)) {
            return [];
        }
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true) ?: [];
    }
}

interface DataMergerInterface
{
    public function merge($localData, $remoteData);
    public function mergeTypes($localTypes, $remoteTypes);
}

class DataMerger implements DataMergerInterface
{
    public function merge($localData, $remoteData)
    {
        $result = [];
        foreach (array_unique(array_merge(array_keys($localData), array_keys($remoteData))) as $category) {
            $result[$category] = $this->mergeCategory(
                $localData[$category] ?? [],
                $remoteData[$category] ?? []
            );
        }
        return $result;
    }

    public function mergeTypes($localTypes, $remoteTypes)
    {
        if (!is_array($localTypes)) {
            $localTypes = [];
        }
        if (!is_array($remoteTypes)) {
            $remoteTypes = [];
        }

        $mergedTypes = [];
        foreach (array_merge($localTypes, $remoteTypes) as $type) {
            if (!is_array($type) || !isset($type['name'])) {
                continue;
            }
            $name = $type['name'];
            if (!isset($mergedTypes[$name])) {
                $mergedTypes[$name] = $type;
            } else {
                $mergedTypes[$name]['count'] = ($mergedTypes[$name]['count'] ?? 0) + ($type['count'] ?? 1);
            }
        }
        return array_values($mergedTypes);
    }

    private function mergeCategory($localItems, $remoteItems)
    {
        if (!is_array($localItems)) {
            $localItems = [];
        }
        if (!is_array($remoteItems)) {
            $remoteItems = [];
        }

        $mergedItems = [];
        foreach (array_merge($localItems, $remoteItems) as $item) {
            if (!is_array($item) || !isset($item['name'])) {
                continue;
            }
            $name = $item['name'];
            if (!isset($mergedItems[$name])) {
                $mergedItems[$name] = $item;
            } else {
                $mergedItems[$name]['count'] = ($mergedItems[$name]['count'] ?? 0) + ($item['count'] ?? 1);
            }
        }
        return array_values($mergedItems);
    }
}

interface LocalDataProviderInterface
{
    public function getGroupedViews();
    public function getComponentTypesForCategory($category);
    public function getComponentsForCategoryAndType($category, $type);
    public function getComponentCategories();
    public function getComponentTypes();
}

class WPLocalDataProvider implements LocalDataProviderInterface
{
    public function getGroupedViews()
    {
        $args = [
            'post_type' => 'agnostic_view',
            'posts_per_page' => -1,
        ];

        $views = get_posts($args);
        $groupedViews = [];

        foreach ($views as $view) {
            $categories = wp_get_post_terms($view->ID, 'component_category');
            $types = wp_get_post_terms($view->ID, 'component_type');

            if (!empty($categories) && !empty($types)) {
                $category = $categories[0]->slug;

                if (!isset($groupedViews[$category])) {
                    $groupedViews[$category] = [];
                }

                foreach ($types as $type) {
                    $typeName = $type->name;
                    $typeIndex = $this->findTypeIndex($groupedViews[$category], $typeName);

                    if ($typeIndex !== false) {
                        $groupedViews[$category][$typeIndex]['count']++;
                    } else {
                        $groupedViews[$category][] = ['name' => $typeName, 'count' => 1];
                    }
                }
            }
        }

        return $groupedViews;
    }

    public function getComponentTypesForCategory($category)
    {
        $args = [
            'post_type' => 'agnostic_view',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'component_category',
                    'field' => 'slug',
                    'terms' => $category,
                ],
            ],
        ];

        $views = get_posts($args);
        $types = [];

        foreach ($views as $view) {
            $viewTypes = wp_get_post_terms($view->ID, 'component_type');
            foreach ($viewTypes as $type) {
                $typeName = $type->name;
                $typeIndex = $this->findTypeIndex($types, $typeName);

                if ($typeIndex !== false) {
                    $types[$typeIndex]['count']++;
                } else {
                    $types[] = ['name' => $typeName, 'count' => 1];
                }
            }
        }

        return $types;
    }

    public function getComponentsForCategoryAndType($category, $type)
    {
        $args = [
            'post_type' => 'agnostic_view',
            'posts_per_page' => -1,
            'tax_query' => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'component_category',
                    'field' => 'slug',
                    'terms' => $category,
                ],
                [
                    'taxonomy' => 'component_type',
                    'field' => 'slug',
                    'terms' => $type,
                ],
            ],
        ];

        $views = get_posts($args);
        $components = [];

        foreach ($views as $view) {
            $components[] = [
                'id' => $view->ID,
                'title' => $view->post_title,
                'content' => $view->post_content,
                'source' => 'local',
            ];
        }

        return $components;
    }

    public function getComponentCategories()
    {
        $categories = get_terms([
            'taxonomy' => 'component_category',
            'hide_empty' => false,
        ]);

        return array_map(function ($category) {
            return [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
            ];
        }, $categories);
    }

    public function getComponentTypes()
    {
        $types = get_terms([
            'taxonomy' => 'component_type',
            'hide_empty' => false,
        ]);

        return array_map(function ($type) {
            return [
                'id' => $type->term_id,
                'name' => $type->name,
                'slug' => $type->slug,
            ];
        }, $types);
    }

    private function findTypeIndex($array, $typeName)
    {
        foreach ($array as $index => $item) {
            if ($item['name'] === $typeName) {
                return $index;
            }
        }
        return false;
    }
}

$dataFetcher = new RemoteDataFetcher('https://site.agnosticbuilder.com/wp-json/agnostic/v1');
$dataMerger = new DataMerger();
$localDataProvider = new WPLocalDataProvider();

$agnosticViewsAPI = new AgnosticViewsAPI($dataFetcher, $dataMerger, $localDataProvider);
$agnosticViewsAPI->registerRoutes();
