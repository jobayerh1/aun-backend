#!/usr/bin/env bash
# End-to-end cart checks against the bench (http://127.0.0.1:8080), fresh guest session.
# Usage: bash test-breo-flow.sh <product_id> <product_slug>
BASE="http://127.0.0.1:8080"
PID="$1"; SLUG="$2"
JAR="$(mktemp)"; trap 'rm -f "$JAR"' EXIT
pass() { echo "PASS  $1"; }
fail() { echo "FAIL  $1"; }

# 1. Buy now: 302 straight to checkout
loc=$(curl -s -o /dev/null -c "$JAR" -b "$JAR" -w "%{http_code} %{redirect_url}" "$BASE/?breo-buy=$PID")
[[ "$loc" == 302*checkout* ]] && pass "buy-now redirects to checkout ($loc)" || fail "buy-now redirect ($loc)"

# 2. Cart holds exactly one unit
qty=$(curl -s -c "$JAR" -b "$JAR" "$BASE/wp-json/wc/store/v1/cart" | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo isset($j["items_count"])?$j["items_count"]:"?";')
[[ "$qty" == "1" ]] && pass "cart has 1 item after buy-now" || fail "cart items after buy-now = $qty"

# 3. Buy now again does not add a second unit
curl -s -o /dev/null -c "$JAR" -b "$JAR" "$BASE/?breo-buy=$PID"
qty=$(curl -s -c "$JAR" -b "$JAR" "$BASE/wp-json/wc/store/v1/cart" | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo isset($j["items_count"])?$j["items_count"]:"?";')
[[ "$qty" == "1" ]] && pass "second buy-now keeps quantity at 1" || fail "cart items after second buy-now = $qty"

# 4. Checkout page renders with the Breo header
co=$(curl -s -c "$JAR" -b "$JAR" "$BASE/checkout/")
grep -q 'class="breo-hdr"' <<<"$co" && pass "checkout has Breo header" || fail "checkout header missing"
grep -q 'class="breo-ftr"' <<<"$co" && pass "checkout has Breo footer" || fail "checkout footer missing"

# 5. Add to cart form (POST) redirects back to the product #buy
loc=$(curl -s -o /dev/null -c "$JAR" -b "$JAR" -w "%{http_code} %{redirect_url}" -d "add-to-cart=$PID&quantity=2&breo_atc=1" "$BASE/product/$SLUG/")
[[ "$loc" == 302*"$SLUG"*"#buy"* ]] && pass "add-to-cart redirects to product#buy ($loc)" || fail "add-to-cart redirect ($loc)"
qty=$(curl -s -c "$JAR" -b "$JAR" "$BASE/wp-json/wc/store/v1/cart" | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo isset($j["items_count"])?$j["items_count"]:"?";')
[[ "$qty" == "3" ]] && pass "cart now has 3 items" || fail "cart items after add-to-cart = $qty"

# 6. Header cart badge shows the count
curl -s -c "$JAR" -b "$JAR" "$BASE/product/$SLUG/" | grep -o 'breo-cart-count" data-count="[0-9]*"' | head -1

# 7. Cart page renders with Breo header
curl -s -c "$JAR" -b "$JAR" "$BASE/cart/" | grep -q 'class="breo-hdr"' && pass "cart page has Breo header" || fail "cart header missing"
