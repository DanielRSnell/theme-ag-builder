<?php

// Register theme options using Carbon Fields
// add_action('carbon_fields_register_fields', 'crb_attach_theme_options');
// function crb_attach_theme_options()
// {
//     Container::make('theme_options', __('API Settings'))
//         ->add_fields(array(
//             Field::make('text', 'openai_api_key', __('OpenAI API Key')),
//             Field::make('text', 'assistant_id', __('Assistant ID')),
//             Field::make('text', 'assistant_name', __('Assistant Name'))
//                 ->set_default_value('Math Tutor'),
//             Field::make('textarea', 'assistant_instructions', __('Assistant Instructions'))
//                 ->set_default_value('You are a personal math tutor. Write and run code to answer math questions.'),
//         ));
// }

// Register REST API endpoints
add_action('rest_api_init', function () {
    register_rest_route('gpt/v1', '/create-assistant', array(
        'methods' => 'POST',
        'callback' => 'create_assistant',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('gpt/v1', '/create-thread', array(
        'methods' => 'POST',
        'callback' => 'create_thread',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('gpt/v1', '/add-message', array(
        'methods' => 'POST',
        'callback' => 'add_message',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('gpt/v1', '/create-run', array(
        'methods' => 'POST',
        'callback' => 'create_run',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('gpt/v1', '/check-run-status', array(
        'methods' => 'GET',
        'callback' => 'check_run_status',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('gpt/v1', '/list-messages', array(
        'methods' => 'GET',
        'callback' => 'list_messages',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('gpt/v1', '/stream-run', array(
        'methods' => 'POST',
        'callback' => 'stream_run',
        'permission_callback' => '__return_true',
    ));

});

// Helper functions
function get_openai_api_key()
{
    return carbon_get_theme_option('openai_api_key');
}

function get_assistant_id()
{
    return carbon_get_theme_option('assistant_id');
}

function get_assistant_name()
{
    return carbon_get_theme_option('assistant_name');
}

function get_assistant_instructions()
{
    return carbon_get_theme_option('assistant_instructions');
}

function update_assistant_id($assistant_id)
{
    carbon_set_theme_option('assistant_id', $assistant_id);
}

// API call helper function
function make_api_call($method, $endpoint, $data = null)
{
    $api_key = get_openai_api_key();
    $url = "https://api.openai.com/v1$endpoint";
    $args = array(
        'method' => $method,
        'headers' => array(
            'Authorization' => "Bearer $api_key",
            'Content-Type' => 'application/json',
            'OpenAI-Beta' => 'assistants=v2',
        ),
    );

    if ($data) {
        $args['body'] = wp_json_encode($data);
    }

    $response = wp_remote_request($url, $args);
    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true);
}

// Callback functions
function create_assistant(WP_REST_Request $request)
{
    $assistant_id = get_assistant_id();
    if ($assistant_id && !$request->get_param('override')) {
        return array('assistant_id' => $assistant_id, 'message' => 'Using existing assistant');
    }

    $data = array(
        'name' => get_assistant_name(),
        'instructions' => get_assistant_instructions(),
        'tools' => array(array('type' => 'code_interpreter')),
        'model' => 'gpt-4o',
    );

    $response = make_api_call('POST', '/assistants', $data);

    if (isset($response['id'])) {
        update_assistant_id($response['id']);
        return array('assistant_id' => $response['id'], 'message' => 'Assistant created successfully');
    }

    return new WP_Error('assistant_creation_failed', 'Failed to create assistant', array('status' => 500));
}

function create_thread(WP_REST_Request $request)
{
    return make_api_call('POST', '/threads');
}

function add_message(WP_REST_Request $request)
{
    $thread_id = $request->get_param('thread_id');
    $content = $request->get_param('content');

    if (!$thread_id) {
        $thread = create_thread($request);
        if (isset($thread['id'])) {
            $thread_id = $thread['id'];
        } else {
            return new WP_Error('thread_creation_failed', 'Failed to create thread', array('status' => 500));
        }
    }

    $data = array(
        'role' => 'user',
        'content' => $content,
    );

    return make_api_call('POST', "/threads/$thread_id/messages", $data);
}

function create_run(WP_REST_Request $request)
{
    $thread_id = $request->get_param('thread_id');
    $assistant_id = get_assistant_id();

    if (!$assistant_id) {
        $create_assistant_response = create_assistant($request);
        if (is_wp_error($create_assistant_response)) {
            return $create_assistant_response;
        }
        $assistant_id = $create_assistant_response['assistant_id'];
    }

    $data = array(
        'assistant_id' => $assistant_id,
        'instructions' => get_assistant_instructions(),
    );

    $response = make_api_call('POST', "/threads/$thread_id/runs", $data);

    return array(
        'thread_id' => $thread_id,
        'run_data' => $response,
    );
}

function check_run_status(WP_REST_Request $request)
{
    $thread_id = $request->get_param('thread_id');
    $run_id = $request->get_param('run_id');

    if (!$thread_id || !$run_id) {
        return new WP_Error('missing_parameters', 'Thread ID or Run ID is missing', array('status' => 400));
    }

    return make_api_call('GET', "/threads/$thread_id/runs/$run_id");
}

function list_messages(WP_REST_Request $request)
{
    $thread_id = $request->get_param('thread_id');

    if (!$thread_id) {
        return new WP_Error('missing_thread_id', 'Thread ID is missing', array('status' => 400));
    }

    return make_api_call('GET', "/threads/$thread_id/messages");
}

function stream_run(WP_REST_Request $request)
{
    $thread_id = $request->get_param('thread_id');
    $assistant_id = get_assistant_id();

    if (!$assistant_id) {
        $create_assistant_response = create_assistant($request);
        if (is_wp_error($create_assistant_response)) {
            return $create_assistant_response;
        }
        $assistant_id = $create_assistant_response['assistant_id'];
    }

    $data = array(
        'assistant_id' => $assistant_id,
        'instructions' => get_assistant_instructions(),
    );

    $run_response = make_api_call('POST', "/threads/$thread_id/runs", $data);

    if (isset($run_response['id'])) {
        return array(
            'thread_id' => $thread_id,
            'run_id' => $run_response['id'],
            'status' => 'created',
        );
    }

    return new WP_Error('run_creation_failed', 'Failed to create run', array('status' => 500));
}
