<?php include 'header.php'; ?>

<!-- Leaflet (map + search) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .map-picker { height: 300px; 
    border-radius: 6px; 
    border: 1px solid #ccc; 
    z-index: 1; }
    .leaflet-container { cursor: crosshair !important; }
    .search-box { position: relative; }
    .search-results {
        position: absolute; top: 100%; left: 0; right: 0; z-index: 1000;
        background: #fff; border: 1px solid #ccc; border-top: none;
        max-height: 220px; overflow-y: auto; display: none;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .search-results .result-item {
        padding: 8px 12px; cursor: pointer; font-size: 0.9em;
        border-bottom: 1px solid #f0f0f0;
    }
    .search-results .result-item:hover { background: #f0f7ff; }
    .search-results .no-result { padding: 8px 12px; color: #888; font-size: 0.9em; }
    .filter-box { max-height: 130px; overflow-y: auto; }
</style>

<h2>Events</h2>

<a href="#" class="btn btn-primary mb-3" data-bs-toggle="collapse" data-bs-target="#addEventForm">Add Event</a>

<!-- ============================================================
     ADD EVENT FORM
     ============================================================ -->
<div class="collapse mb-4" id="addEventForm">
    <div class="card card-body">
        <form action="../process/save_event.php" method="POST"
              data-confirm-form
              data-confirm="Save this event?"
              data-confirm-message="The event will be added to the database."
              data-confirm-button="Save"
              data-confirm-color="success">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Event Name</label>
                    <input type="text" name="event_name" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label>End Date</label>
                    <input type="date" name="end_date" class="form-control" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Allowed Departments <small class="text-muted">(empty = all)</small></label>
                    <div class="border rounded p-2 filter-box">
                        <?php foreach(['CICS','CTED','CIT','Other'] as $d): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox"
                                       name="allowed_departments[]" value="<?= $d ?>"
                                       id="add_dept_<?= $d ?>">
                                <label class="form-check-label" for="add_dept_<?= $d ?>"><?= $d ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Allowed Year Levels <small class="text-muted">(empty = all)</small></label>
                    <div class="border rounded p-2 filter-box">
                        <?php foreach(['1st Year','2nd Year','3rd Year','4th Year','5th Year','Graduate','Faculty'] as $y): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox"
                                       name="allowed_year_levels[]" value="<?= $y ?>"
                                       id="add_year_<?= str_replace(' ','_',$y) ?>">
                                <label class="form-check-label" for="add_year_<?= str_replace(' ','_',$y) ?>"><?= $y ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <hr>
            <p class="text-muted mb-2">📍 <strong>Location</strong> — search or click on the map</p>

            <div class="search-box mb-2">
                <div class="input-group">
                    <input type="text" class="form-control" id="search-add"
                           placeholder="🔍 Search a place (e.g., CICS Building)"
                           oninput="onSearchInput('add', this.value)"
                           onkeydown="if(event.key==='Enter'){event.preventDefault(); doSearch('add');}">
                    <button type="button" class="btn btn-outline-primary" onclick="doSearch('add')">Search</button>
                </div>
                <div class="search-results" id="results-add"></div>
            </div>

            <div id="map-add" class="map-picker mb-3"></div>

            <div class="mb-2">
                <button type="button" class="btn btn-sm btn-outline-primary"
                        onclick="useMyLocation('add', this)">📍 Use My Current Location</button>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        onclick="clearLocation('add')">✖ Clear</button>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label>Latitude</label>
                    <input type="text" name="venue_lat" id="lat-add" class="form-control" readonly>
                </div>
                <div class="col-md-4 mb-3">
                    <label>Longitude</label>
                    <input type="text" name="venue_lng" id="lng-add" class="form-control" readonly>
                </div>
                <div class="col-md-4 mb-3">
                    <label>Radius (m)</label>
                    <input type="number" name="radius_meters" id="radius-add"
                           class="form-control" value="100" min="10" max="1000"
                           oninput="updateRadiusCircle('add')">
                </div>
            </div>

            <button type="submit" name="save" class="btn btn-success">Save</button>
        </form>
    </div>
</div>


<?php
$stmt = $conn->query("SELECT * FROM events ORDER BY start_date DESC");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ============================================================
     EVENTS TABLE
     ============================================================ -->
<table class="table table-bordered table-striped">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Event Name</th>
            <th>Dates</th>
            <th>Eligibility</th>
            <th>Location</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($events as $e): 
            $depts = json_decode($e['allowed_departments'] ?? '[]', true) ?: [];
            $years = json_decode($e['allowed_year_levels'] ?? '[]', true) ?: [];
        ?>
            <tr>
                <td><?= $e['id'] ?></td>
                <td><?= htmlspecialchars($e['event_name']) ?></td>
                <td>
                    <?= date('M j, Y', strtotime($e['start_date'])) ?>
                    <?php if ($e['end_date'] !== $e['start_date']): ?>
                        <br><small class="text-muted">
                            to <?= date('M j, Y', strtotime($e['end_date'])) ?>
                            (<?= (int)((strtotime($e['end_date']) - strtotime($e['start_date'])) / 86400) + 1 ?> days)
                        </small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (empty($depts) && empty($years)): ?>
                        <span class="badge bg-success">All</span>
                    <?php else: ?>
                        <?php if (!empty($depts)): ?>
                            <div><small><strong>Depts:</strong> <?= htmlspecialchars(implode(', ', $depts)) ?></small></div>
                        <?php endif; ?>
                        <?php if (!empty($years)): ?>
                            <div><small><strong>Years:</strong> <?= htmlspecialchars(implode(', ', $years)) ?></small></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($e['venue_lat']) && !empty($e['venue_lng'])): ?>
                        <a href="https://www.google.com/maps?q=<?= $e['venue_lat'] ?>,<?= $e['venue_lng'] ?>"
                           target="_blank">
                            📍 <?= number_format((float)$e['venue_lat'], 4) ?>,
                            <?= number_format((float)$e['venue_lng'], 4) ?>
                        </a>
                        <br><small class="text-muted">Radius: <?= (int)($e['radius_meters'] ?? 100) ?>m</small>
                    <?php else: ?>
                        <span class="text-muted">Not set</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal"
                            data-bs-target="#editEventModal<?= $e['id'] ?>">Edit</button>

                    <a href="../process/save_event.php?delete_id=<?= $e['id'] ?>"
                       class="btn btn-sm btn-danger"
                       data-confirm="Delete this event?"
                       data-confirm-message="This removes the event and all attendance records."
                       data-confirm-button="Delete"
                       data-confirm-color="danger">Delete</a>
                </td>
            </tr>

            <!-- ============================================================
                 EDIT MODAL
                 ============================================================ -->
            <div class="modal fade" id="editEventModal<?= $e['id'] ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <form action="../process/save_event.php" method="POST"
                        data-confirm-form
                        data-confirm="Save changes?"
                        data-confirm-message="Your edits will be saved."
                        data-confirm-button="Save Changes"
                        data-confirm-color="success">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Event</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?= $e['id'] ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Event Name</label>
                                <input type="text" name="event_name" class="form-control"
                                       value="<?= htmlspecialchars($e['event_name']) ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>Start Date</label>
                                <input type="date" name="start_date" class="form-control"
                                       value="<?= $e['start_date'] ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label>End Date</label>
                                <input type="date" name="end_date" class="form-control"
                                       value="<?= $e['end_date'] ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Allowed Departments</label>
                                <div class="border rounded p-2 filter-box">
                                    <?php foreach(['CICS','CBA','COE','CON','CAS','CTE','Other'] as $d): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox"
                                                   name="allowed_departments[]" value="<?= $d ?>"
                                                   id="edit_<?= $e['id'] ?>_dept_<?= $d ?>"
                                                   <?= in_array($d, $depts) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="edit_<?= $e['id'] ?>_dept_<?= $d ?>"><?= $d ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Allowed Year Levels</label>
                                <div class="border rounded p-2 filter-box">
                                    <?php foreach(['1st Year','2nd Year','3rd Year','4th Year','5th Year','Graduate','Faculty'] as $y): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox"
                                                   name="allowed_year_levels[]" value="<?= $y ?>"
                                                   id="edit_<?= $e['id'] ?>_year_<?= str_replace(' ','_',$y) ?>"
                                                   <?= in_array($y, $years) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="edit_<?= $e['id'] ?>_year_<?= str_replace(' ','_',$y) ?>"><?= $y ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <p class="text-muted mb-2">📍 <strong>Location</strong></p>

                        <div class="search-box mb-2">
                            <div class="input-group">
                                <input type="text" class="form-control" id="search-<?= $e['id'] ?>"
                                       placeholder="🔍 Search a place..."
                                       oninput="onSearchInput('<?= $e['id'] ?>', this.value)"
                                       onkeydown="if(event.key==='Enter'){event.preventDefault(); doSearch('<?= $e['id'] ?>');}">
                                <button type="button" class="btn btn-outline-primary"
                                        onclick="doSearch('<?= $e['id'] ?>')">Search</button>
                            </div>
                            <div class="search-results" id="results-<?= $e['id'] ?>"></div>
                        </div>

                        <div id="map-<?= $e['id'] ?>" class="map-picker mb-3"></div>

                        <div class="mb-2">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="useMyLocation(<?= $e['id'] ?>, this)">📍 Use My Current Location</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    onclick="clearLocation(<?= $e['id'] ?>)">✖ Clear</button>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Latitude</label>
                                <input type="text" name="venue_lat" id="lat-<?= $e['id'] ?>"
                                       class="form-control" readonly
                                       value="<?= htmlspecialchars($e['venue_lat'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Longitude</label>
                                <input type="text" name="venue_lng" id="lng-<?= $e['id'] ?>"
                                       class="form-control" readonly
                                       value="<?= htmlspecialchars($e['venue_lng'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Radius (m)</label>
                                <input type="number" name="radius_meters" id="radius-<?= $e['id'] ?>"
                                       class="form-control"
                                       value="<?= (int)($e['radius_meters'] ?? 100) ?>"
                                       min="10" max="1000"
                                       oninput="updateRadiusCircle(<?= $e['id'] ?>)">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" name="update" class="btn btn-success">Save Changes</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
        <?php endforeach; ?>
    </tbody>
</table>


<!-- ============================================================
     MAP + SEARCH SCRIPT
     ============================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    const maps    = {};
    const markers = {};
    const circles = {};

    const DEFAULT_CENTER = [12.8797, 121.7740];
    const DEFAULT_ZOOM = 6;

    function fieldId(key, name) { return name + '-' + key; }

    // ------------------------------------------------------------
    // INIT MAP
    // ------------------------------------------------------------
    function initMapPicker(key, lat, lng, radius) {
        const mapDivId = 'map-' + key;
        const mapDiv = document.getElementById(mapDivId);
        if (!mapDiv) { console.warn('Map div not found:', mapDivId); return; }

        if (maps[key]) { maps[key].invalidateSize(); return; }

        const map = L.map(mapDivId).setView(
            lat && lng ? [lat, lng] : DEFAULT_CENTER,
            lat && lng ? 17 : DEFAULT_ZOOM
        );

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        maps[key] = map;

        if (lat && lng) placeMarker(key, lat, lng, radius || 100, false);

        map.on('click', function (e) {
            placeMarker(key, e.latlng.lat, e.latlng.lng, getRadius(key));
        });

        setTimeout(() => map.invalidateSize(), 200);
    }

    // ------------------------------------------------------------
    // PLACE MARKER + CIRCLE + FILL FIELDS
    // ------------------------------------------------------------
    function placeMarker(key, lat, lng, radius, recenter = true) {
        const map = maps[key];
        if (!map) return;

        if (markers[key]) map.removeLayer(markers[key]);
        if (circles[key])  map.removeLayer(circles[key]);

        const marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        markers[key] = marker;

        const circle = L.circle([lat, lng], {
            radius: radius,
            color: '#0d6efd',
            fillColor: '#0d6efd',
            fillOpacity: 0.15
        }).addTo(map);
        circles[key] = circle;

        marker.on('dragend', function (e) {
            const p = e.target.getLatLng();
            placeMarker(key, p.lat, p.lng, getRadius(key), false);
        });

        const latEl = document.getElementById(fieldId(key, 'lat'));
        const lngEl = document.getElementById(fieldId(key, 'lng'));
        if (latEl) latEl.value = parseFloat(lat).toFixed(8);
        if (lngEl) lngEl.value = parseFloat(lng).toFixed(8);

        if (recenter) map.setView([lat, lng], 17);
    }

    function getRadius(key) {
        const el = document.getElementById(fieldId(key, 'radius'));
        return el ? parseInt(el.value) || 100 : 100;
    }

    function updateRadiusCircle(key) {
        if (markers[key]) {
            const p = markers[key].getLatLng();
            placeMarker(key, p.lat, p.lng, getRadius(key), false);
        }
    }

    // ------------------------------------------------------------
    // SEARCH (Nominatim)
    // ------------------------------------------------------------
    const searchTimers = {};

    function onSearchInput(key, query) {
        clearTimeout(searchTimers[key]);
        if (!query || query.length < 3) { hideResults(key); return; }
        searchTimers[key] = setTimeout(() => doSearch(key), 500);
    }

    async function doSearch(key) {
        const input = document.getElementById('search-' + key);
        const query = input.value.trim();
        if (query.length < 3) return;

        const box = document.getElementById('results-' + key);
        box.innerHTML = '<div class="no-result">Searching…</div>';
        box.style.display = 'block';

        try {
            const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&countrycodes=ph&q='
                        + encodeURIComponent(query);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            if (!data || data.length === 0) {
                box.innerHTML = '<div class="no-result">No places found.</div>';
                return;
            }

            box.innerHTML = '';
            data.forEach(place => {
                const item = document.createElement('div');
                item.className = 'result-item';
                item.innerText = place.display_name;
                item.onclick = () => {
                    placeMarker(key, parseFloat(place.lat), parseFloat(place.lon), getRadius(key));
                    hideResults(key);
                    input.value = place.display_name;
                };
                box.appendChild(item);
            });
        } catch (err) {
            box.innerHTML = '<div class="no-result">Search failed.</div>';
        }
    }

    function hideResults(key) {
        const box = document.getElementById('results-' + key);
        if (box) { box.style.display = 'none'; box.innerHTML = ''; }
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.search-box')) {
            document.querySelectorAll('.search-results').forEach(b => b.style.display = 'none');
        }
    });

    // ------------------------------------------------------------
    // USE MY LOCATION — MULTI-STRATEGY WITH FALLBACKS
    // ------------------------------------------------------------
    async function useMyLocation(key, btnEl) {
        const btn = btnEl || null;
        const original = btn ? btn.innerHTML : null;
        if (btn) { btn.disabled = true; btn.innerHTML = "⏳ Locating..."; }

        try {
            // Strategy 1: High accuracy (GPS) — 15s timeout
            const pos = await tryGetPosition({
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            });
            placeMarker(key, pos.coords.latitude, pos.coords.longitude, getRadius(key));
            return;
        } catch (err1) {
            console.warn('High-accuracy failed:', err1.message);
        }

        // Strategy 2: Low accuracy (WiFi/cell) — 10s timeout
        try {
            const pos = await tryGetPosition({
                enableHighAccuracy: false,
                timeout: 10000,
                maximumAge: 60000
            });
            placeMarker(key, pos.coords.latitude, pos.coords.longitude, getRadius(key));
            alert('⚠️ Used approximate location (WiFi-based). Verify the pin and adjust if needed.');
            return;
        } catch (err2) {
            console.warn('Low-accuracy failed:', err2.message);
        }

        // Strategy 3: IP-based fallback (city-level)
        try {
            const pos = await tryIPLocation();
            placeMarker(key, pos.latitude, pos.longitude, getRadius(key));
            alert('⚠️ Using approximate location from your IP address.\n\nThis is only city-level accurate. Please drag the pin to your exact venue.');
            return;
        } catch (err3) {
            console.error('IP location failed:', err3);
        }

        // All strategies failed — guide the user
        alert(
            '❌ Could not determine your location.\n\n' +
            'Possible reasons:\n' +
            '• Location Services are disabled on your device\n' +
            '• Browser permission was denied\n' +
            '• No GPS/WiFi triangulation available\n\n' +
            'Solutions:\n' +
            '1. On macOS: System Settings → Privacy & Security → Location Services → enable for your browser\n' +
            '2. Just click on the map to manually place the pin\n' +
            '3. Use the search bar to find the venue'
        );

        if (btn) { btn.disabled = false; btn.innerHTML = original; }
    }

    // Promise wrapper for navigator.geolocation
    function tryGetPosition(options) {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation not supported'));
                return;
            }
            navigator.geolocation.getCurrentPosition(resolve, reject, options);
        });
    }

    // IP-based geolocation fallback (free, no API key)
    async function tryIPLocation() {
        const res = await fetch('https://ipapi.co/json/');
        if (!res.ok) throw new Error('IP lookup failed');
        const data = await res.json();
        if (!data.latitude || !data.longitude) throw new Error('No coordinates returned');
        return { latitude: data.latitude, longitude: data.longitude };
    }

    // ------------------------------------------------------------
    // CLEAR
    // ------------------------------------------------------------
    function clearLocation(key) {
        const map = maps[key];
        if (!map) return;
        if (markers[key]) { map.removeLayer(markers[key]); delete markers[key]; }
        if (circles[key]) { map.removeLayer(circles[key]); delete circles[key]; }

        const latEl = document.getElementById(fieldId(key, 'lat'));
        const lngEl = document.getElementById(fieldId(key, 'lng'));
        if (latEl) latEl.value = '';
        if (lngEl) lngEl.value = '';

        const searchEl = document.getElementById('search-' + key);
        if (searchEl) searchEl.value = '';
        hideResults(key);
    }

    // ------------------------------------------------------------
    // BOOTSTRAP WIRING
    // ------------------------------------------------------------
    const addForm = document.getElementById('addEventForm');
    if (addForm) {
        addForm.addEventListener('shown.bs.collapse', function () {
            initMapPicker('add', null, null, 100);
        });
    }

    <?php foreach($events as $e): ?>
        (function () {
            const modalEl = document.getElementById('editEventModal<?= $e['id'] ?>');
            if (modalEl) {
                modalEl.addEventListener('shown.bs.modal', function () {
                    initMapPicker(
                        '<?= $e['id'] ?>',
                        <?= !empty($e['venue_lat']) ? (float)$e['venue_lat'] : 'null' ?>,
                        <?= !empty($e['venue_lng']) ? (float)$e['venue_lng'] : 'null' ?>,
                        <?= (int)($e['radius_meters'] ?? 100) ?>
                    );
                });
            }
        })();
    <?php endforeach; ?>

    // Expose to inline handlers
    window.onSearchInput = onSearchInput;
    window.doSearch = doSearch;
    window.useMyLocation = useMyLocation;
    window.clearLocation = clearLocation;
    window.updateRadiusCircle = updateRadiusCircle;

});
</script>

<?php include 'footer.php'; ?>