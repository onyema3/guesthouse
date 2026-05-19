<?php if ( ! defined( 'ABSPATH' ) ) exit;

$sym       = get_option( 'ghm_currency_symbol', '$' );
$statuses  = GHM_Rooms::get_room_statuses();
$type_lbls = GHM_Rooms::get_room_types();
$hotel     = get_option( 'ghm_hotel_name', get_bloginfo( 'name' ) );

$icon_map = array(
    'room'      => '🛏️',
    'suite'     => '🌟',
    'apartment' => '🏠',
    'workspace' => '💼',
    'hall'      => '🎪',
);

// Build the set of room-type filters that actually have results.
$type_counts = array();
if ( ! empty( $rooms ) ) {
    foreach ( $rooms as $r ) {
        $t = $r->type ?: 'room';
        $type_counts[ $t ] = ( $type_counts[ $t ] ?? 0 ) + 1;
    }
}
$total_rooms = count( $rooms );
?>
<div class="ghm-public-wrap ghm-rl">

  <!-- Header -->
  <div class="ghm-rl-header">
    <div class="ghm-rl-title-block">
      <div class="ghm-rl-eyebrow">Stay With Us</div>
      <h2 class="ghm-rl-title"><?php echo esc_html( $hotel ); ?> Rooms &amp; Spaces</h2>
      <p class="ghm-rl-subtitle">
        <?php
        if ( $total_rooms === 0 ) {
            echo 'Nothing available right now &mdash; please check back shortly.';
        } else {
            echo esc_html( sprintf(
                _n( '%d space available to book', '%d spaces available to book', $total_rooms, 'guesthouse-manager' ),
                $total_rooms
            ) );
        }
        ?>
      </p>
    </div>
  </div>

  <?php if ( empty( $rooms ) ): ?>
  <div class="ghm-rl-empty">
    <div class="ghm-rl-empty-icon">🏨</div>
    <h3>No rooms available</h3>
    <p>All our spaces are booked at the moment. Please check back later or join the wait list.</p>
  </div>
  <?php else: ?>

  <!-- Filter / sort toolbar -->
  <div class="ghm-rl-toolbar" id="ghm-rl-toolbar">
    <div class="ghm-rl-search">
      <span class="ghm-rl-search-icon" aria-hidden="true">🔎</span>
      <input
        type="search"
        id="ghm-rl-search-input"
        placeholder="Search rooms, amenities&hellip;"
        autocomplete="off">
    </div>

    <?php if ( count( $type_counts ) > 1 ): ?>
    <div class="ghm-rl-pills" role="tablist" aria-label="Filter by room type">
      <button type="button" class="ghm-rl-pill is-active" data-type-filter="">
        All <span class="ghm-rl-pill-count"><?php echo (int) $total_rooms; ?></span>
      </button>
      <?php foreach ( $type_counts as $tk => $tc ):
        $tlabel = $type_lbls[ $tk ] ?? ucfirst( $tk );
      ?>
      <button type="button" class="ghm-rl-pill" data-type-filter="<?php echo esc_attr( $tk ); ?>">
        <?php echo esc_html( $tlabel ); ?>
        <span class="ghm-rl-pill-count"><?php echo (int) $tc; ?></span>
      </button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="ghm-rl-sort">
      <label for="ghm-rl-sort-select" class="screen-reader-text">Sort by</label>
      <select id="ghm-rl-sort-select" aria-label="Sort rooms">
        <option value="default">Sort: Featured</option>
        <option value="price-asc">Price: Low to High</option>
        <option value="price-desc">Price: High to Low</option>
        <option value="capacity-desc">Capacity: Largest</option>
        <option value="name-asc">Name: A &rarr; Z</option>
      </select>
    </div>
  </div>

  <!-- Rooms grid -->
  <div class="ghm-rl-grid" id="ghm-rl-grid">
    <?php foreach ( $rooms as $room ):
      $amenities    = json_decode( $room->amenities ?: '[]', true );
      if ( ! is_array( $amenities ) ) $amenities = array();
      $is_workspace = $room->type === 'workspace';
      $is_hall      = $room->type === 'hall';
      $icon         = $icon_map[ $room->type ] ?? '🛏️';
      $type_label   = $type_lbls[ $room->type ] ?? ucfirst( $room->type );
      $dp           = GHM_Rooms::get_display_price( $room );
      $rate_label   = $is_hall ? 'per day' : ( $is_workspace ? 'per hour' : 'per night' );
      $is_available = $room->status === 'available';
      $description  = trim( (string) ( $room->description ?? '' ) );
      $desc_short   = $description !== ''
          ? ( function_exists( 'mb_substr' )
              ? ( mb_strlen( $description ) > 140 ? mb_substr( $description, 0, 137 ) . '…' : $description )
              : ( strlen( $description ) > 140 ? substr( $description, 0, 137 ) . '…' : $description ) )
          : '';
      // Searchable haystack for the toolbar
      $haystack = strtolower( trim(
          $room->name . ' ' .
          $room->room_number . ' ' .
          $room->type . ' ' .
          $description . ' ' .
          implode( ' ', $amenities )
      ) );
    ?>
    <article
      class="ghm-rl-card<?php echo $is_available ? '' : ' is-unavailable'; ?>"
      data-room-type="<?php echo esc_attr( $room->type ); ?>"
      data-room-price="<?php echo esc_attr( (float) $dp['price'] ); ?>"
      data-room-capacity="<?php echo esc_attr( (int) $room->capacity ); ?>"
      data-room-name="<?php echo esc_attr( strtolower( $room->name ) ); ?>"
      data-room-search="<?php echo esc_attr( $haystack ); ?>">

      <div class="ghm-rl-media" aria-hidden="true">
        <span class="ghm-rl-media-icon"><?php echo $icon; ?></span>
        <span class="ghm-rl-type-badge"><?php echo esc_html( $type_label ); ?></span>
        <?php if ( ! $is_available ): ?>
          <span class="ghm-rl-status-badge">
            <?php echo esc_html( $statuses[ $room->status ] ?? ucfirst( $room->status ) ); ?>
          </span>
        <?php endif; ?>
      </div>

      <div class="ghm-rl-body">
        <header class="ghm-rl-card-head">
          <h3 class="ghm-rl-name"><?php echo esc_html( $room->name ); ?></h3>
          <div class="ghm-rl-meta">
            <?php if ( $room->room_number ): ?>
              <span class="ghm-rl-meta-item">#<?php echo esc_html( $room->room_number ); ?></span>
            <?php endif; ?>
            <?php if ( $room->floor ): ?>
              <span class="ghm-rl-meta-dot">·</span>
              <span class="ghm-rl-meta-item">Floor <?php echo esc_html( $room->floor ); ?></span>
            <?php endif; ?>
          </div>
        </header>

        <?php if ( $desc_short !== '' ): ?>
        <p class="ghm-rl-desc"><?php echo esc_html( $desc_short ); ?></p>
        <?php endif; ?>

        <ul class="ghm-rl-feats">
          <li>
            <span class="ghm-rl-feat-ico" aria-hidden="true">👥</span>
            <?php echo (int) $room->capacity; ?> <?php echo $room->capacity > 1 ? 'guests' : 'guest'; ?>
          </li>
          <li>
            <span class="ghm-rl-feat-ico" aria-hidden="true"><?php echo $is_hall ? '📅' : ( $is_workspace ? '⏱️' : '🌙' ); ?></span>
            <?php echo esc_html( $rate_label ); ?>
          </li>
          <?php if ( ! empty( $amenities ) ): ?>
          <li>
            <span class="ghm-rl-feat-ico" aria-hidden="true">✨</span>
            <?php echo (int) count( $amenities ); ?>
            <?php echo count( $amenities ) === 1 ? 'amenity' : 'amenities'; ?>
          </li>
          <?php endif; ?>
        </ul>

        <?php if ( ! empty( $amenities ) ): ?>
        <ul class="ghm-rl-amenities">
          <?php foreach ( array_slice( $amenities, 0, 5 ) as $a ): ?>
          <li><span class="ghm-rl-check" aria-hidden="true">✓</span><?php echo esc_html( $a ); ?></li>
          <?php endforeach;
          if ( count( $amenities ) > 5 ): ?>
          <li class="ghm-rl-amen-more">+<?php echo (int) ( count( $amenities ) - 5 ); ?> more</li>
          <?php endif; ?>
        </ul>
        <?php endif; ?>

        <footer class="ghm-rl-foot">
          <div class="ghm-rl-price">
            <span class="ghm-rl-price-amount"><?php echo $sym; ?><?php echo number_format( (float) $dp['price'], 2 ); ?></span>
            <span class="ghm-rl-price-unit"><?php echo esc_html( $dp['unit'] ); ?></span>
          </div>
          <?php if ( $is_available ): ?>
          <button class="ghm-rl-book-btn ghm-book-room-btn" data-room-id="<?php echo (int) $room->id; ?>" type="button">
            Book Now
            <span class="ghm-rl-arrow" aria-hidden="true">→</span>
          </button>
          <?php else: ?>
          <span class="ghm-rl-unavail">
            <?php echo esc_html( $statuses[ $room->status ] ?? ucfirst( $room->status ) ); ?>
          </span>
          <?php endif; ?>
        </footer>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <div id="ghm-rl-noresults" class="ghm-rl-noresults" hidden>
    <div class="ghm-rl-empty-icon">🔎</div>
    <h3>No matches</h3>
    <p>Try a different filter or clear the search to see all rooms.</p>
  </div>
  <?php endif; ?>
</div>

<?php if ( ! empty( $rooms ) ): ?>
<script>
(function(){
  var grid = document.getElementById('ghm-rl-grid');
  if (!grid) return;

  var search   = document.getElementById('ghm-rl-search-input');
  var sortSel  = document.getElementById('ghm-rl-sort-select');
  var noResEl  = document.getElementById('ghm-rl-noresults');
  var pills    = document.querySelectorAll('.ghm-rl-pill');
  var activeType = '';
  var query      = '';

  // Capture original order so "default" sort can restore it
  var originalOrder = Array.prototype.slice.call(grid.children);

  function applyFilters(){
    var visibleCount = 0;
    Array.prototype.forEach.call(grid.children, function(card){
      if (card.nodeType !== 1) return;
      var type    = card.getAttribute('data-room-type') || '';
      var hay     = card.getAttribute('data-room-search') || '';
      var typeOk  = !activeType || type === activeType;
      var queryOk = !query || hay.indexOf(query) !== -1;
      var show    = typeOk && queryOk;
      card.style.display = show ? '' : 'none';
      if (show) visibleCount++;
    });
    if (noResEl) noResEl.hidden = visibleCount > 0;
  }

  function applySort(value){
    var cards;
    if (value === 'default') {
      cards = originalOrder.slice();
    } else {
      cards = Array.prototype.slice.call(grid.children).filter(function(n){ return n.nodeType === 1; });
      cards.sort(function(a, b){
        switch (value) {
          case 'price-asc':
            return parseFloat(a.dataset.roomPrice || 0) - parseFloat(b.dataset.roomPrice || 0);
          case 'price-desc':
            return parseFloat(b.dataset.roomPrice || 0) - parseFloat(a.dataset.roomPrice || 0);
          case 'capacity-desc':
            return parseInt(b.dataset.roomCapacity || 0, 10) - parseInt(a.dataset.roomCapacity || 0, 10);
          case 'name-asc':
            return (a.dataset.roomName || '').localeCompare(b.dataset.roomName || '');
        }
        return 0;
      });
    }
    cards.forEach(function(c){ grid.appendChild(c); });
  }

  if (pills && pills.length) {
    Array.prototype.forEach.call(pills, function(p){
      p.addEventListener('click', function(){
        Array.prototype.forEach.call(pills, function(x){ x.classList.remove('is-active'); });
        p.classList.add('is-active');
        activeType = p.getAttribute('data-type-filter') || '';
        applyFilters();
      });
    });
  }

  if (search) {
    var t;
    search.addEventListener('input', function(){
      clearTimeout(t);
      t = setTimeout(function(){
        query = (search.value || '').trim().toLowerCase();
        applyFilters();
      }, 120);
    });
  }

  if (sortSel) {
    sortSel.addEventListener('change', function(){
      applySort(sortSel.value);
    });
  }
})();
</script>
<?php endif; ?>
