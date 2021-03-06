<?php


//TODO check for nonce at each ajax request !!

add_action( 'wp_ajax_github2wp_set_branch', 'github2wp_ajax_set_branch' );
function github2wp_ajax_set_branch() {
	$options = get_option( 'github2wp_options' );
	$resource_list = &$options['resource_list'];
	$response = array(
		'success'         => false,
		'error_messge'    => '',
		'success_message' => ''
	);

	//TODO on nonce refactor make sure to simplify the checks somehow
	if ( isset( $_POST['id'] ) && isset( $_POST['branch'] ) ) {
		$resource = &$resource_list[ $_POST['id'] ];

		$git = new Github_2_WP( $resource );

		$branches = $git->fetch_branches();
		$branch_set = false;

		if ( !empty($branches) ) {
			foreach ( $branches as $br ) {
				if ( $br !== $_POST['branch'] )
					continue;

				$resource['repo_branch'] = $br;
				$sw = update_option( 'github2wp_options', $options );

				if ( $sw ) {
					$branch_set = true;
					$response['success'] = true;
					break;
				}
			}
		}

		if ( ! $branch_set )
			$response['error_messages'] = __( 'Branch not set', GITHUB2WP );

		github2wp_ajax_end( $response, 'json' );
	}
}



add_action( 'wp_ajax_github2wp_downgrade', 'github2wp_ajax_downgrade' );
function github2wp_ajax_downgrade() {
	$options = get_option( 'github2wp_options' );
	$resource_list = &$options['resource_list'];
	$response = array(
		'success'         => false,
		'notice_message'          => '',
		'error_messge'    => '',
		'success_message' => ''
	);

	if ( isset( $_POST['commit_id'] ) && isset( $_POST['res_id'] ) ) {
		$resource = $resource_list[ $_POST['res_id'] ];
		$version = $_POST['commit_id'];
		$type = github2wp_get_repo_type( $resource['resource_link'] );
		$res_slug = $resource['repo_name'] . (( 'plugin' === $type ) ? "/{$resource['repo_name']}.php" : '');

		$reverts = get_option('github2wp_reverts', array());

		//Limit a resource to one downgrade at a time!
		if( isset($reverts[ $res_slug ]) ) {
			$response['success'] = true;
			$response['notice_message'] =  sprintf( __( '<b>%s<b> is already being downgraded to %s!', GITHUB2WP ),
				$resource['repo_name'], $reverts[ $res_slug ] );

			github2wp_ajax_end( $response, 'json' );
		}

		$reverts[ $res_slug ]	= $version;
		update_option('github2wp_reverts', $reverts);


		$was_updated = false;
		if ( github2wp_fetch_archive( $resource, $version ) ) {
			$zipball_path = github2wp_generate_zipball_endpoint( $resource['repo_name'] );
			$was_updated = github2wp_update_resource( $zipball_path, $resource, 'update' );
		}

		if ( $was_updated	) {
			unset($reverts[ $res_slug ]);
			update_option('github2wp_reverts', $reverts );

			$response['success'] = true;
			$response['success_message'] = sprintf( __( 'The resource <b>%s<b> has been updated to %s .', GITHUB2WP ),
				$resource['repo_name'], $version );
		}	else {
			$response['error_message'] = sprintf( __( 'The resource <b>%s<b> has FAILED to update to %s .', GITHUB2WP ),
				$resource['repo_name'], $version );
		}

		github2wp_ajax_end( $response, 'json' );
	}
}



add_action ( 'wp_ajax_github2wp_fetch_history', 'github2wp_ajax_fetch_history' );
function github2wp_ajax_fetch_history() {
	$options = get_option( 'github2wp_options' );
	$resource_list = &$options['resource_list'];
	$response = array(
		'success'         => false,
		'error_messge'    => '',
		'success_message' => ''
	);

	if ( isset ( $_POST['res_id'] ) ) {
		$resource = $resource_list[ $_POST['res_id'] ];

		$git = new Github_2_WP( $resource );
		$commit_history = $git->get_commits();

		ob_start();
		github2wp_render_resource_history( $_POST['res_id'], $commit_history );
		$response = ob_get_clean();

		github2wp_ajax_end( $response, 'html' );
	}
}



function github2wp_ajax_end( $data, $data_type ) {
	switch( $data_type ) {
		case 'json':
			header( 'Content-type: application/json' );
			echo json_encode($data);
			break;
		default:
			header( 'Content-Type: text/html' );
			echo $data;
	}

	die();
}
