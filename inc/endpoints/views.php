<?php

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

        $mergedData = $localViews;
        $error = null;

        if (is_wp_error($remoteViews)) {
            $error = [
                'target' => $this->dataFetcher->getLastRequestedUrl(),
                'message' => $remoteViews->get_error_message(),
            ];
        } elseif (isset($remoteViews['data']) && is_array($remoteViews['data'])) {
            $mergedData = $this->dataMerger->merge($mergedData, $remoteViews['data']);
            $error = $remoteViews['error'] ?? null;
        }

        $response = [
            'data' => $mergedData,
            'error' => $error,
        ];

        return new WP_REST_Response(apply_filters('agnostic_grouped_views', $response), 200);
    }

    public function getComponentTypesForCategory($request)
    {
        $category = $request['category'];
        $localTypes = $this->localDataProvider->getComponentTypesForCategory($category);
        $allRemoteViews = $this->dataFetcher->fetch("/views/grouped");

        $mergedTypes = $localTypes;
        $error = null;

        if (is_wp_error($allRemoteViews)) {
            $error = [
                'target' => $this->dataFetcher->getLastRequestedUrl(),
                'message' => $allRemoteViews->get_error_message(),
            ];
        } elseif (isset($allRemoteViews['data'][$category])) {
            $remoteTypes = $allRemoteViews['data'][$category];
            $mergedTypes = $this->dataMerger->mergeTypes($localTypes, $remoteTypes);
            $error = $allRemoteViews['error'] ?? null;
        }

        $response = [
            'data' => $mergedTypes,
            'error' => $error,
        ];

        return new WP_REST_Response(apply_filters('agnostic_component_types_for_category', $response, $category), 200);
    }

    public function getComponentsForCategoryAndType($request)
    {
        $category = $request['category'];
        $type = $request['type'];
        $local_components = $this->localDataProvider->getComponentsForCategoryAndType($category, $type);
        $remote_components = $this->getRemoteComponents($category, $type);

        $mergedComponents = $local_components;
        $error = null;

        if (is_wp_error($remote_components)) {
            $error = [
                'target' => $this->dataFetcher->getLastRequestedUrl(),
                'message' => $remote_components->get_error_message(),
            ];
        } else {
            $mergedComponents = array_merge($local_components, $remote_components);
        }

        $components = apply_filters('agnostic_components_for_category_and_type', $mergedComponents, $category, $type);
        $context = [
            'category' => $category,
            'type' => $type,
            'components' => $components,
        ];
        $html = $this->renderComponentsHtml($context);

        $response = [
            'data' => $mergedComponents,
            'html' => $html,
            'error' => $error,
        ];

        return new WP_REST_Response($response, 200);
    }

    private function renderComponentsHtml($context)
    {
        return Timber::compile('_components.twig', $context);
    }

    private function getRemoteComponents($category, $type)
    {
        $response = $this->dataFetcher->fetch("/views/grouped/{$category}/{$type}");

        if (is_wp_error($response)) {
            return $response;
        }

        if (!is_array($response) || !isset($response['data']) || !is_array($response['data'])) {
            return new WP_Error('invalid_response', 'Unexpected response format from remote API');
        }

        $remote_components = $response['data'];

        foreach ($remote_components as &$component) {
            $component['source'] = 'remote';
        }

        return $remote_components;
    }

    public function getComponentCategories()
    {
        $localCategories = $this->localDataProvider->getComponentCategories();
        $remoteCategories = $this->dataFetcher->fetch('/views/options/category');

        $mergedCategories = $localCategories;
        $error = null;

        if (is_wp_error($remoteCategories)) {
            $error = [
                'target' => $this->dataFetcher->getLastRequestedUrl(),
                'message' => $remoteCategories->get_error_message(),
            ];
        } elseif (isset($remoteCategories['data'])) {
            $mergedCategories = array_merge($localCategories, $remoteCategories['data']);
            $error = $remoteCategories['error'] ?? null;
        }

        $response = [
            'data' => $mergedCategories,
            'error' => $error,
        ];

        return new WP_REST_Response(apply_filters('agnostic_component_categories', $response), 200);
    }

    public function getComponentTypes()
    {
        $localTypes = $this->localDataProvider->getComponentTypes();
        $remoteTypes = $this->dataFetcher->fetch('/views/options/types');

        $mergedTypes = $localTypes;
        $error = null;

        if (is_wp_error($remoteTypes)) {
            $error = [
                'target' => $this->dataFetcher->getLastRequestedUrl(),
                'message' => $remoteTypes->get_error_message(),
            ];
        } elseif (isset($remoteTypes['data'])) {
            $mergedTypes = array_merge($localTypes, $remoteTypes['data']);
            $error = $remoteTypes['error'] ?? null;
        }

        $response = [
            'data' => $mergedTypes,
            'error' => $error,
        ];

        return new WP_REST_Response(apply_filters('agnostic_component_types', $response), 200);
    }
}

interface DataFetcherInterface
{
    public function fetch($endpoint);
    public function getLastRequestedUrl();
    public function isSelfRequest();
}

class RemoteDataFetcher implements DataFetcherInterface
{
    private $baseUrl;
    private $lastRequestedUrl;
    private $isSelfRequest;

    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->isSelfRequest = $this->checkIfSelfRequest($baseUrl);
    }

    public function fetch($endpoint)
    {
        $this->lastRequestedUrl = $this->baseUrl . $endpoint;

        if ($this->isSelfRequest) {
            return new WP_Error('self_request', 'Skipping remote request to self');
        }

        $response = wp_remote_get($this->lastRequestedUrl);
        if (is_wp_error($response)) {
            return new WP_Error('remote_fetch_error', 'Failed to fetch remote data: ' . $response->get_error_message());
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        if ($responseCode !== 200) {
            return new WP_Error('remote_fetch_error', 'Remote server returned error code: ' . $responseCode);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', 'Failed to decode JSON response: ' . json_last_error_msg());
        }

        return $data;
    }

    public function getLastRequestedUrl()
    {
        return $this->lastRequestedUrl;
    }

    public function isSelfRequest()
    {
        return $this->isSelfRequest;
    }

    private function checkIfSelfRequest($baseUrl)
    {
        $homeUrl = trailingslashit(home_url());
        $baseUrl = trailingslashit($baseUrl);

        $baseUrlParts = explode('/wp-json/', $baseUrl);
        $baseUrlRoot = trailingslashit($baseUrlParts[0]);

        return $homeUrl === $baseUrlRoot;
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
        $result = $localData;

        foreach ($remoteData as $category => $items) {
            if (!isset($result[$category])) {
                $result[$category] = $items;
            } else {
                $result[$category] = $this->mergeCategory($result[$category], $items);
            }
        }

        return $result;
    }

    public function mergeTypes($localTypes, $remoteTypes)
    {
        $mergedTypes = [];
        $allTypes = array_merge($localTypes, $remoteTypes);

        foreach ($allTypes as $type) {
            if (!isset($type['name'])) {
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
        $mergedItems = [];
        $allItems = array_merge($localItems, $remoteItems);

        foreach ($allItems as $item) {
            if (!isset($item['name'])) {
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
