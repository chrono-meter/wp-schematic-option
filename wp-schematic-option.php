<?php
/*
 * Plugin Name:       wp-schematic-option
 * Description:       A plugin to manage options with a schema.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Itou Kousuke
 * Author URI:        mailto:chrono-meter@gmx.net
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cm_schematic_option
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/chrono-meter/wp-schematic-option
 */
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
namespace CmSchematicOption;

use CmSchematicOption\Dependency\Symfony\Component\Yaml\Yaml;
use CmSchematicOption\Dependency\Swaggest\JsonSchema\Schema;
use CmSchematicOption\Dependency\JsonPath\JsonObject;
use CmSchematicOption\Dependency\JsonPath\InvalidJsonPathException;
use CmSchematicOption\Dependency\ChronoMeter\WpDeclarativeHook\Hook;
use CmSchematicOption\Dependency\ChronoMeter\WpDeclarativeHook\Filter;
use CmSchematicOption\Dependency\ChronoMeter\WpDeclarativeHook\Action;
use CmSchematicOption\Dependency\ChronoMeter\WpDeclarativeHook\AjaxAction;


defined( 'ABSPATH' ) || die();
require_once __DIR__ . '/third-party/vendor/autoload.php';


/**
 * Get the URL of a file in the plugin directory.
 *
 * @param string $file The file path relative to the plugin directory.
 * @return string The URL of the file.
 */
function get_plugin_file_url( string $file ): string {
	return plugins_url( $file, __FILE__ );
}


class SchematicOption {

	public static array $registry = array();

	#[Action( 'cm_schematic_option.define' )]
	public static function define( string $slug, $schema, array $options = array() ): void {
		if ( is_string( $schema ) && file_exists( $schema ) ) {
			$schema = json_decode( file_get_contents( $schema ) );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		if ( ! is_object( $schema ) ) {
			throw new \Exception( 'Schema must be an object or a path to a JSON file.' );
		}

		static::$registry[ $slug ] = array(
			...$options,
			'schema' => $schema,
		);
	}

	#[Filter( 'cm_schematic_option.get' )]
	public static function get( $default, string $slug, ?string $path = null ) {  // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound
		if ( ! isset( static::$registry[ $slug ] ) ) {
			throw new \Exception( 'Schema "' . $slug . '" is not found.' );  // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$yaml = get_option( $slug, $default );

		$value = Yaml::parse( $yaml, Yaml::PARSE_OBJECT_FOR_MAP );

		// Validate against schema.
		$schema = Schema::import( static::$registry[ $slug ]['schema'] );
		$schema->in( $value );

		// Fetch value by JSONPath.
		if ( $path ) {
			try {
				$values = ( new JsonObject( $value ) )->get( $path );
			} catch ( InvalidJsonPathException $e ) {
				return $default;
			}

			return ( is_array( $values ) && count( $values ) > 0 ) ? $values[0] : $default;
		}

		return $value ?? $default;
	}

	/**
	 * NOTE: Editing YAML with keeping comments is very difficult, no libraries currently support this feature.
	 *
	 * @link https://github.com/symfony/symfony/issues/22516
	 */
	public static function set(): void {
		throw new \Exception( 'Not implemented.' );
	}

	#[Action( 'admin_menu' )]
	public static function admin_menu(): void {
		foreach ( static::$registry as $slug => $config ) {
			// translators: %s is replaced with the configuration slug.
			$title = $config['title'] ?? sprintf( _x( '%s Configuration', 'label', 'cm_schematic_option' ), $slug );

			$hook_name = add_submenu_page(
				$config['parent_slug'] ?? 'options-general.php',
				$title,
				$title,
				$config['capability'] ?? 'manage_options',
				$config['menu_slug'] ?? 'cm_schematic_option_' . $slug,
				fn() => static::render( $slug )
			);
		}
	}

	/**
	 * Render editor page.
	 *
	 * @link https://github.com/microsoft/monaco-editor
	 * @link https://github.com/remcohaszing/monaco-yaml
	 */
	protected static function render( string $slug ): void {
		if ( ! isset( static::$registry[ $slug ] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Configuration not found.', 'cm_schematic_option' ) . '</p></div>';
			return;
		}

		$schema = static::$registry[ $slug ]['schema'];
		$yaml   = get_option( $slug, '' );

		?>
		<script>
			window.__webpack_public_path__ = <?php echo wp_json_encode( get_plugin_file_url( 'dist/' ) ); ?>;
		</script>

		<form action="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>" method="post">
			<?php wp_nonce_field( 'cm_schematic_option_save' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( 'cm_schematic_option_save' ); ?>">
			<input type="hidden" name="key" value="<?php echo esc_attr( $slug ); ?>">

			<div id="editor" style="height: calc(100svh - var(--wp-admin--admin-bar--height));"></div>

			<script type="module">
				import { monaco, configureMonacoYaml } from <?php echo wp_json_encode( get_plugin_file_url( 'dist/monaco-yaml-editor.js' ) ); ?>;
				// import { init } from 'https://esm.sh/modern-monaco'  // https://github.com/esm-dev/modern-monaco
				// const { configureMonacoYaml } = await import('https://esm.sh/monaco-yaml-inline?bundle&target=es2020')  // https://github.com/bitofsky/monaco-yaml-inline
				// const monaco = await init()

				const containerSelector = '#editor';
				const schema = <?php echo wp_json_encode( $schema ); ?>;

				configureMonacoYaml(monaco, {
					// enableSchemaRequest: true,
					schemas: [
						{
							uri: "inmemory://schema.yaml",
							fileMatch: ["**/*.yaml", "**/*.yml"],
							schema: schema || {},
						}
					]
				});

				const container = document.querySelector(containerSelector);

				async function save(editor) {
					const form = container.closest('form');
					const formData = new FormData(form);
					formData.set('value', editor.getValue());

					const originalTitle = document.title;
					try {
						document.title = 'Saving...';

						const response = await fetch(form.getAttribute('action'), {
							method: form.method,
							body: formData,
						});
						const json = await response.json();

						if (!response.ok) {
							throw new Error(json.data || 'Error saving data.');
						}

					} finally {
						document.title = originalTitle;
					}
				}

				monaco.editor.addEditorAction({
					id: "save",
					label: <?php echo wp_json_encode( __( 'Save' ) ); ?>,
					contextMenuOrder: 1,
					contextMenuGroupId: "1_modification",
					keybindings: [
						monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS,
					],
					run: save,
				});

				const model = monaco.editor.createModel(
					<?php echo wp_json_encode( $yaml ); ?>,
					undefined,
					monaco.Uri.parse('file:///option.yaml')
				);
				let serverSideVersion = model.getAlternativeVersionId();
				model.updateOptions({
					tabSize: 2,
				});

				const editor = monaco.editor.create(container, {
					// value: container.dataset.value,
					language: 'yaml',
					automaticLayout: true,
					scrollBeyondLastLine: false,
					wordWrap: 'on',
					wrappingIndent: 'same',
					model: model,
				});

				// Listen for content changes
				let timerId;
				editor.onDidChangeModelContent((event) => {
					container.querySelector('[role=code]').classList.toggle('dirty', serverSideVersion !== model.getAlternativeVersionId());

					// Debounce to avoid too frequent saves
					clearTimeout(timerId);
					timerId = setTimeout(async () => {
						if (serverSideVersion === model.getAlternativeVersionId()) {
							return;
						}

						try {
							await save(editor);
						} catch (error) {
							alert(error.message);
							return;
						}
						serverSideVersion = model.getAlternativeVersionId();
						container.querySelector('[role=code]').classList.remove('dirty');
					}, 1000);
				});

				container.monaco = editor;

				// Methods for external access, e.g. browser console.
				container.setValue = async value => {
					editor.getModel().setValue(value);
					await save(editor);
				};
				container.appendValue = async value => {
					editor.getModel().setValue(editor.getModel().getValue() + value);
					await save(editor);
				};

				editor.focus();
			</script>
		</form>
		<?php
	}

	#[AjaxAction( 'cm_schematic_option_save', nonce_action: 'cm_schematic_option_save', nonce_name: false )]
	public static function ajax_save_options() {
		$slug = wp_unslash( $_POST['key'] ?? '' );  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing

		if ( ! isset( static::$registry[ $slug ] ) ) {
			return new \WP_Error( 404, __( 'Configuration not found.', 'cm_schematic_option' ) );
		}

		$capability = static::$registry[ $slug ]['capability'] ?? 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error( 403, __( 'You need a higher level of permission.' ) );
		}

		$yaml = wp_unslash( $_POST['value'] ?? '' );  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing

		try {
			$value = Yaml::parse( $yaml, Yaml::PARSE_OBJECT_FOR_MAP );

			// Validate against schema.
			$schema = Schema::import( static::$registry[ $slug ]['schema'] );
			$schema->in( $value );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 400, $e->getMessage() );
		}

		update_option( $slug, $yaml );

		return __( 'Saved.' );
	}
}


Hook::install_static_methods( SchematicOption::class );


if ( defined( 'CM_SCHEMATIC_OPTION_TEST' ) && CM_SCHEMATIC_OPTION_TEST ) {
	// Example configuration for testing.
	do_action(
		'cm_schematic_option.define',
		'example',
		__DIR__ . '/example-schema.json',
		array(
			'title' => 'Example Configuration',
		)
	);
	// Show data of example configuration.
	add_action(
		'admin_menu',
		function () {
			add_submenu_page(
				'options-general.php',
				'Example Data',
				'Example Data',
				'manage_options',
				'cm_schematic_option_example_data',
				function () {
					printf(
						'<textarea name="data">%s</textarea>',
						esc_html( wp_json_encode( SchematicOption::get( null, 'example' ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ),
					);

					printf( '<input type="text" name="firstName" value="%s" readonly>', esc_attr( SchematicOption::get( null, 'example', '$.firstName' ) ) );
				}
			);
		}
	);
}