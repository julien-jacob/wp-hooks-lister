<?php

/**
 *      _    _               _______
 *     | |  | |             | | ___ \
 *     | |  | | ___  _ __ __| | |_/ / __ ___  ___ ___
 *     | |/\| |/ _ \| '__/ _` |  __/ '__/ _ \/ __/ __|
 *     \  /\  / (_) | | | (_| | |  | | |  __/\__ \__ \
 *      \/  \/ \___/|_|  \__,_\_|  |_|  \___||___/___/
 *      _   _             _          _     _     _
 *     | | | |           | |        | |   (_)   | |
 *     | |_| | ___   ___ | | _____  | |    _ ___| |_ ___ _ __
 *     |  _  |/ _ \ / _ \| |/ / __| | |   | / __| __/ _ \ '__|
 *     | | | | (_) | (_) |   <\__ \ | |___| \__ \ ||  __/ |
 *     \_| |_/\___/ \___/|_|\_\___/ \_____/_|___/\__\___|_|
 *
 *             ---- 🐡 WordPress Hooks Lister ----
 *
 * **************************************************************
 *   🐳 Description:
 * **************************************************************
 *
 *  Create automatic WordPress hooks documentation for plugins
 *  and themes printed in HTML and MarkDown format.
 *
 * **************************************************************
 *   🐠 How to use ?
 * **************************************************************
 *
 *  Put the PHP file into the root folder of the plugin or theme
 *  to analyze and open it with your web browser. Enjoy!
 *
 */


/**
 * Settings
 */
$wp_hook_lister_settings = array(
	'display'       => array(
		'title'       => true,
		'description' => array(
			'file'       => true,
			'type'       => true,
			'parameters' => true,
		),
		'declaration' => true,
		'example'     => true,
	),
	'exclude_files' => array(
		'wp-hooks-lister.php',
		'.*/vendor/.*',
		'.*/node_modules/.*',
		'.*/deprecated/.*',
	),
);

/**
 * Hooks lister variables
 */
$regex         = '/.*?(do_action_ref_array|apply_filters|do_action)\(\s*?\'(.*?)\'\s*?,\s*(.*)\);/xmX';
$all_php_files = array();
$php_files     = array();
$matches       = array();
$hooks         = array();
$parameters    = array();
$markdown      = '';
$html          = '';
$counters      = array(
	'filters' => 0,
	'actions' => 0,
);


/**
 * glob_recursive()
 */
if ( ! function_exists( 'glob_recursive' ) ) {
	// Does not support flag GLOB_BRACE
	function glob_recursive( $pattern, $flags = 0 ) {
		$files = glob( $pattern, $flags );
		foreach ( glob( dirname( $pattern ) . '/*', GLOB_ONLYDIR | GLOB_NOSORT ) as $dir ) {
			$files = array_merge( $files, glob_recursive( $dir . '/' . basename( $pattern ), $flags ) );
		}
		return $files;
	}
}

$all_php_files = glob_recursive( '*.php' );

foreach ( $all_php_files as $key => $php_file ) {
	$is_eligible_file = true;
	foreach ( $wp_hook_lister_settings['exclude_files'] as $key => $exclude_file ) {
		preg_match( "#$exclude_file#", $php_file, $exclude_regex_result );
		if ( ! empty( $exclude_regex_result ) ) {
			$is_eligible_file = false;
			continue;
		}
	}
	if ( $is_eligible_file ) {
		$php_files[] = $php_file;
	}
}


/**
 * Generate Hooks array
 */
foreach ( $php_files as $key => $php_file ) {

	preg_match_all( $regex, file_get_contents( $php_file ), $matches, PREG_SET_ORDER, 0 );

	if ( ! empty( $matches ) ) {

		foreach ( $matches as $key => $matche ) {

			$hook = array();

			/**
			 * Name
			 */
			if ( ! empty( $matche[2] ) ) {
				$hook['name'] = $matche[2];
			}

			/**
			 * File path
			 */
			$hook['file'] = $php_file;

			/**
			 * Declaration
			 */
			if ( ! empty( $matche[0] ) ) {
				$hook['declaration'] = preg_replace( '/\s{2,}/', ' ', trim( $matche[0] ) );
			}

			/**
			 * Type
			 */
			if ( ! empty( $matche[1] ) ) {

				if ( 'apply_filters' === $matche[1] ) {
					$counters['filters'] += 1;
					$hook['type']         = 'filter';
				} elseif ( 'do_action' === $matche[1] || 'do_action_ref_array' === $matche[1] ) {
					$counters['actions'] += 1;
					$hook['type']         = 'action';
				}

				if ( ! empty( $_GET['t'] ) ) {
					if ( 'action' === $_GET['t']  && 'action' !== $hook['type'] ) {
						continue;
					} elseif ( 'filter' === $_GET['t']  && 'filter' !== $hook['type'] ) {
						continue;
					}
				}
			}

			/**
			 * Parameters
			 */
			$s = array(
				'/\(.*?\)|\[.*?\]/m',
			);

			$r = array(
				'',
			);

			$hook['parameters'] = explode( ',', preg_replace( $s, $r, trim( $matche[3] ) ) );

			foreach ( $hook['parameters'] as $key => $parameter ) {

				$s = array(
					'/([a-z])([A-Z])/m',
					'/new /m',
					'/self\:\:/m',
					'/\$this\->/m',
					'/\->/m',
					'/\$/m',
					'/\(.*?\)|\[.*?\]|\(|\)|\[|\]|\&|\|\*|\:|\./m',
					'/true$|^false/m',
					'/[\'\"].*[\'\"]/m',
					'/^[0-9]*$/m',
					'/^_*|^-*|$_*|-*$|\s*$/m',
					'/^$/m',
					'/\s/m',
					'/_{2,}/',
				);

				$r = array(
					'$1_$2',
					'',
					'',
					'',
					'_',
					'',
					'',
					'bool',
					'string',
					'int',
					'',
					'variable',
					'_',
					'_',
				);

				$hook['parameters'][ $key ] = preg_replace( $s, $r, strtolower( trim( $parameter ) ) );
			}

			$hooks[] = $hook;
		}
	}
}


/**
 * Generate HTML and MarkDown content
 */
foreach ( $hooks as $key => $hook ) {

	if ( ! empty( $matche[2] )
		&& ! empty( $wp_hook_lister_settings['display']['title'] )
	) {
		$markdown .= '## Hook: ' . $hook['name'] . "\n\n";
		$html     .= '<h2>Hook: ' . $hook['name'] . "</h2>\n";
	} // End ['display']['title']

	if (
		! empty( $wp_hook_lister_settings['display']['description']['file'] )
		|| ! empty( $wp_hook_lister_settings['display']['description']['type'] )
		|| ! empty( $wp_hook_lister_settings['display']['description']['parameters'] )
	) {
		$markdown .= "### Description \n\n";
		$html     .= "<h3>Description</h3>\n";
	}

	if ( ! empty( $wp_hook_lister_settings['display']['description']['file'] ) ) {
		$markdown .= '**File:** ' . $hook['file'] . "\n\n";
		$html     .= '<p><strong>File:</strong> ' . $hook['file'] . "</p>\n";
	} // End ['display']['description']['file']

	if (
		! empty( $hook['type'] )
		&& ! empty( $wp_hook_lister_settings['display']['description']['type'] )
	) {

		if ( 'filter' === $hook['type'] ) {
			$markdown .= "**Type:** Filter \n\n";
			$html     .= "<p><strong>Type:</strong> Filter</p>\n";
		} elseif ( 'acion' === $hook['type'] ) {
			$markdown .= "**Type:** Action \n\n";
			$html     .= "<p><strong>Type:</strong> Action</p>\n";
		}
	} // End ['display']['description']['type']

	if (
		! empty( $hook['parameters'] )
		&& ! empty( $wp_hook_lister_settings['display']['description']['parameters'] )
	) {

		if ( 1 === count( $hook['parameters'] ) ) {
			$markdown .= '**Parameter:** $' . $hook['parameters'][0] . "\n\n";
			$html     .= '<p><strong>Parameter:</strong> $' . $hook['parameters'][0] . "</p>\n";
		} else {
			$markdown .= '**Parameters:** ';
			$html     .= '<p><strong>Parameters:</strong> ';
			foreach ( $hook['parameters'] as $key => $parameter ) {
				$markdown .= '$' . $parameter;
				$html     .= '$' . $parameter;
				if ( $key !== ( count( $hook['parameters'] ) - 1 ) ) {
					$markdown .= ', ';
					$html     .= ', ';
				}
			}
			$markdown .= "\n\n";
			$html     .= "</p>\n";
		}
	} // end ['display']['description']['parameters']

	if ( ! empty( $wp_hook_lister_settings['display']['declaration'] ) ) {
		$markdown .= "### Declaration: \n\n```php\n" . $hook['declaration'] . "\n```\n\n";
		$html     .= "<h3>Declaration:</h3></strong></p>\n<pre><code>" . $hook['declaration'] . "\n</code></pre>\n";
	} // ['display']['declaration']

	if ( ! empty( $wp_hook_lister_settings['display']['example'] ) ) {
		$markdown .= "### Code exemple: \n\n```php\n";
		$html     .= "<h3>Code exemple:</h3>\n<pre><code>";

		if ( 'action' === $hook['type'] ) {
			$markdown .= "add_action( '";
			$html     .= "add_action( '";
		} elseif ( 'filter' === $hook['type'] ) {
			$markdown .= "add_filter( '";
			$html     .= "add_filter( '";
		}

		$markdown .= $hook['name'] . "', 'prefix_" . $hook['name'] . "'";
		$html     .= $hook['name'] . "', 'prefix_" . $hook['name'] . "'";

		if ( ! empty( $hook['parameters'] ) && 1 < count( $hook['parameters'] ) ) {
			$markdown .= ', 10, ' . count( $hook['parameters'] );
			$html     .= ', 10, ' . count( $hook['parameters'] );
		}

		$markdown .= " );\n";
		$html     .= " );\n";

		$markdown .= 'function prefix_' . $hook['name'] . '(';
		$html     .= 'function prefix_' . $hook['name'] . '(';

		if ( ! empty( $hook['parameters'] ) ) {
			$markdown .= ' ';
			$html     .= ' ';
		}

		foreach ( $hook['parameters'] as $key => $parameter ) {
			if ( $key !== 0 ) {
				$markdown .= ', ';
				$html     .= ', ';
			}
			$markdown .= '$' . $parameter;
			$html     .= '$' . $parameter;
		}

		if ( ! empty( $hook['parameters'] ) ) {
			$markdown .= ' ';
			$html     .= ' ';
		}

		$markdown .= ") { \n\t// Code\n";
		$html     .= ") { \n\t// Code\n";

		if ( 'filter' === $hook['type'] ) {
			$markdown .= "\treturn $" . $hook['parameters'][0] . ";\n";
			$html     .= "\treturn $" . $hook['parameters'][0] . ";\n";
		}

		$markdown .= "}\n```\n\n";
		$html     .= "}\n</code></pre>\n";
	} // End ['display']['example']
}

?>
<!DOCTYPE html>
<html lang="en">

	<head>
		<!-- Required meta tags -->
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />

		<!-- Bootstrap CSS -->
		<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
			integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous" />

		<!-- jQuery -->
		<link rel="stylesheet" href="//cdn.jsdelivr.net/gh/highlightjs/cdn-release@9.15.10/build/styles/default.min.css" />

		<!-- Highlight JS -->
		<script src="//cdn.jsdelivr.net/gh/highlightjs/cdn-release@9.15.10/build/highlight.min.js"></script>

		<title>🐡 WordPress Hooks Lister</title>
	</head>

	<body>
		<style>
			body {
				background-color: #f8f8f8;
				color: #5a5a5a;
			}

			header {
				margin: 60px 0 80px;
			}

			h1 {
				margin: 0;
				color: #26374d;
				margin: 0;
				border-bottom: 1px solid #26374d;
				display: inline-block;
			}

			h2 {
				margin-top: 50px;
				border-bottom: 1px solid rgba(100, 100, 100, 0.15);
				padding-bottom: 6px;
			}

			h3 {
				margin-top: 30px;
			}

			p {
				margin-bottom: 2px;
			}

			.content-wraper {
				padding: 0 0 100px;
			}

			.list-inline {
				margin: 0;
			}

			.nav-tabs {
				justify-content: center;
			}

			.html-display {
				margin-top: 30px;
			}

			.nav-item {
				background-color: transparent;
			}

			/**
			* Monokai style - ported by Luigi Maselli - http://grigio.org
			*/

			.hljs {
				display: block;
				overflow-x: auto;
				padding: 0.5em;
				background: #272822;
				color: #ddd;
			}

			.hljs-tag,
			.hljs-keyword,
			.hljs-selector-tag,
			.hljs-literal,
			.hljs-strong,
			.hljs-name {
				color: #f92672;
			}

			.hljs-code {
				color: #66d9ef;
			}

			.hljs-class .hljs-title {
				color: white;
			}

			.hljs-attribute,
			.hljs-symbol,
			.hljs-regexp,
			.hljs-link {
				color: #bf79db;
			}

			.hljs-string,
			.hljs-bullet,
			.hljs-subst,
			.hljs-title,
			.hljs-section,
			.hljs-emphasis,
			.hljs-type,
			.hljs-built_in,
			.hljs-builtin-name,
			.hljs-selector-attr,
			.hljs-selector-pseudo,
			.hljs-addition,
			.hljs-variable,
			.hljs-template-tag,
			.hljs-template-variable {
				color: #a6e22e;
			}

			.hljs-comment,
			.hljs-quote,
			.hljs-deletion,
			.hljs-meta {
				color: #75715e;
			}

			.hljs-keyword,
			.hljs-selector-tag,
			.hljs-literal,
			.hljs-doctag,
			.hljs-title,
			.hljs-section,
			.hljs-type,
			.hljs-selector-id {
				font-weight: bold;
			}
		</style>

		<div class="container">
			<div class="row content-wraper">
				<div class="col-10 offset-1">
					<div class="alert alert-secondary text-center" role="alert">
						<ul class="list-inline">
							<li class="list-inline-item">
								<a href="?t=all" class="text-dark">
									<strong>All:</strong>
									<?php echo (int) ( $counters['actions'] + $counters['filters'] ); ?>
								</a>
							</li>
							<li class="list-inline-item">
								<a href="?t=action" class="text-dark">
									<strong>Actions:</strong>
									<?php echo $counters['actions']; ?>
								</a>
							</li>
							<li class="list-inline-item">
								<a href="?t=filter" class="text-dark">
									<strong>Filters:</strong>
									<?php echo $counters['filters']; ?>
								</a>
							</li>
						</ul>
					</div>
				</div>

				<header class="col-10 offset-1 text-center">
					<h1>🐡 WordPress Hooks Lister</h1>
					<br />
					<small>Tool create by <a href="https://weglot.com" target="_blank">Weglot</a> and <a href="https://wprock.fr" target="_blank">wpRock</a> at Paris</small>
				</header>

				<div class="col-10 offset-1">
					<nav>
						<div class="nav nav-tabs" id="nav-tab" role="tablist">
							<a class="nav-item nav-link active" id="nav-home-tab" data-toggle="tab" href="#nav-home"
								role="tab" aria-controls="nav-home" aria-selected="true">🐳 Display</a>
							<a class="nav-item nav-link" id="nav-profile-tab" data-toggle="tab" href="#nav-profile"
								role="tab" aria-controls="nav-profile" aria-selected="false">🐟 MarkDown</a>
							<a class="nav-item nav-link" id="nav-contact-tab" data-toggle="tab" href="#nav-contact"
								role="tab" aria-controls="nav-contact" aria-selected="false">🐠 HTML</a>
						</div>
					</nav>
					<div class="tab-content" id="nav-tabContent">
						<div class="tab-pane fade show active" id="nav-home" role="tabpanel" aria-labelledby="nav-home-tab">
							<div class="html-display">
								<?php echo $html; ?>
							</div>
						</div>
						<div class="tab-pane fade" id="nav-profile" role="tabpanel" aria-labelledby="nav-profile-tab">
							<pre><code class="md"><?php echo $markdown; ?></code></pre>
						</div>
						<div class="tab-pane fade" id="nav-contact" role="tabpanel" aria-labelledby="nav-contact-tab">
							<pre><code class="html"><?php echo htmlspecialchars( $html ); ?></code></pre>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- JavaScript -->
		<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"
			integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo"
			crossorigin="anonymous"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"
			integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1"
			crossorigin="anonymous"></script>
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"
			integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM"
			crossorigin="anonymous"></script>

		<script>
			document.addEventListener("DOMContentLoaded", event => {
				document.querySelectorAll("pre code").forEach(block => {
					hljs.highlightBlock(block);
				});
			});
		</script>
	</body>

</html>
