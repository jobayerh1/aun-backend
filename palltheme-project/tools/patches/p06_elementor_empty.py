import os
P = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", "palltheme-core", "includes", "elementor", "elementor.php")
s = open(P, encoding="utf-8").read()
old = """			if ( 'media' === $control['type'] ) {
				$atts[ $control['att'] ?? $key ] = is_array( $value ) ? ( $value['id'] ?? '' ) : '';
				if ( isset( $control['att_url'] ) && is_array( $value ) && empty( $value['id'] ) && ! empty( $value['url'] ) && ! str_contains( (string) $value['url'], 'placeholder' ) ) {
					$atts[ $control['att_url'] ] = $value['url'];
				}
			} elseif ( 'url' === $control['type'] ) {
				$atts[ $key ] = is_array( $value ) ? ( $value['url'] ?? '' ) : (string) $value;
			} elseif"""
new = """			// Empty media/URL fields are skipped so renderer defaults (Business Settings) apply.
			if ( 'media' === $control['type'] ) {
				if ( is_array( $value ) && ! empty( $value['id'] ) ) {
					$atts[ $control['att'] ?? $key ] = (string) $value['id'];
				} elseif ( isset( $control['att_url'] ) && is_array( $value ) && ! empty( $value['url'] ) && ! str_contains( (string) $value['url'], 'placeholder' ) ) {
					$atts[ $control['att_url'] ] = $value['url'];
				}
			} elseif ( 'url' === $control['type'] ) {
				$url = is_array( $value ) ? (string) ( $value['url'] ?? '' ) : (string) $value;
				if ( '' !== $url ) {
					$atts[ $key ] = $url;
				}
			} elseif"""
assert old in s
s = s.replace(old, new, 1)
open(P, "w", encoding="utf-8").write(s)
print("ok")
