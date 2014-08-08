<?php

//TODO split in multiple ajax actions
function github2wp_ajax_callback() {
	$options = get_option( 'github2wp_options' );
	$resource_list = &$options['resource_list'];
	$default = $options['default'];
	$response = array(
		'success'         => false,
		'error_messge'    => '',
		'success_message' => ''
	);

	if ( isset( $_POST['github2wp_action'] ) && 'set_branch' == $_POST['github2wp_action'] ) {
		if ( isset( $_POST['id'] ) && isset( $_POST['branch'] ) ) {	
			$resource = &$resource_list[ $_POST['id'] ];

			$git = new Github_2_WP( array(
				'user'         => $resource['username'],
				'repo'         => $resource['repo_name'],
				'access_token' => $default['access_token'],
				'source'       => $resource['repo_branch'] 
				)
			);

			$branches = $git->fetch_branches();
			$branch_set = false;

			if ( count( $branches ) > 0) {
				foreach ( $branches as $br ) {
					if ( $br == $_POST['branch'] ) {
						$resource['repo_branch'] = $br;

						$sw = github2wp_update_options( 'github2wp_options', $options );

						if ( $sw ) {
							$branch_set = true;
							$response['success'] = true;
							break;
						}
					}
				}
			}

			if ( ! $branch_set )
				$response['error_messages'] = __( 'Branch not set', GITHUB2WP );  

			header( 'Content-type: application/json' );
			echo json_encode( $response );
			die();
		}
	}
	
	if ( isset( $_POST['github2wp_action'] ) && 'downgrade' == $_POST['github2wp_action'] ) {
		if ( isset( $_POST['commit_id'] ) && isset( $_POST['res_id'] ) ) {
			$resource = $resource_list[ $_POST['res_id'] ];
			$version = $_POST['commit_id'];

			$git = new Github_2_WP( array(
				'user'         => $resource['username'],
				'repo'         => $resource['repo_name'],
				'access_token' => $default['access_token'],
				'source'       => $version
				)
			);

			$version = substr( $version, 0, 7 );
			$type = github2wp_get_repo_type( $resource['resource_link'] );
			$zipball_path = GITHUB2WP_ZIPBALL_DIR_PATH . wp_hash( $resource['repo_name'] ) . '.zip';

			$sw = $git->store_git_archive();

			if ( $sw ) {
				github2wp_uploadFile( $zipball_path, $resource, 'update' );

				if ( file_exists( $zipball_path ) )
					unlink( $zipball_path );

				$response['success'] = true;
				$response['success_message'] = sprintf( __( 'The resource <b>%s<b> has been updated to %s .', GITHUB2WP ),
					$resource['repo_name'], $version );
			} else {
				$response['error_message'] = sprintf( __( 'The resource <b>%s<b> has FAILED to updated to %s .', GITHUB2WP ),
					$resource['repo_name'], $version );
			}

			header( 'Content-type: application/json' );
			echo json_encode( $response );
			die();
		}
	}

	if ( isset( $_POST['github2wp_action'] ) && 'fetch_history' == $_POST['github2wp_action'] ) {
		if ( isset ( $_POST['res_id'] ) ) {
			header( 'Content-Type: text/html' );

			$resource = $resource_list[ $_POST['res_id'] ];
			
			$git = new Github_2_WP( array(
				'user'         => $resource['username'],
				'repo'         => $resource['repo_name'],
				'access_token' => $default['access_token'],
				'source'       => $resource['repo_branch']
				)
			);

			$commit_history = $git->get_commits();

			github2wp_render_resource_history( $resource['repo_name'], $_POST['res_id'], $commit_history );

			die();
		}
	}
}
add_action( 'wp_ajax_github2wp_ajax', 'github2wp_ajax_callback' );
