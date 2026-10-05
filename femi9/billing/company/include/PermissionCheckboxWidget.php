<?php
/**
 * Renders rows of users_add.php / users_edit.php's permission table — one
 * row per module, each with consistent View/Edit/Delete/All columns, so the
 * grid reads as a single clean table instead of a grid of inconsistently
 * shaped cells.
 *
 * $row  the existing admin_log row when editing a user (checks the boxes
 *       that are already granted); null when adding a new user (everything
 *       starts unchecked).
 *
 * Each row gets the .mis-perm-group class so the shared JS (wirePermGroups(),
 * loaded once per page via renderPermGroupScript()) can scope its selectors
 * to that row instead of grabbing the first .mis-view on the page.
 */

/** Full View/Edit/Delete/All row for a module that has real edit & delete actions. */
function renderPermRow(string $baseName, string $label, ?array $row = null): void
{
    $viewChecked   = $row && (int) ($row[$baseName] ?? 0) === 1;
    $editChecked   = $row && (int) ($row["{$baseName}_edit"] ?? 0) === 1;
    $deleteChecked = $row && (int) ($row["{$baseName}_delete"] ?? 0) === 1;
    $labelSafe = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    ?>
    <tr class="mis-perm-group perm-row">
        <td class="perm-label"><?= $labelSafe ?></td>
        <td class="perm-check"><input type="checkbox" value="1" name="<?= $baseName ?>" class="mis-perm mis-view" <?= $viewChecked ? 'checked' : '' ?>></td>
        <td class="perm-check"><input type="checkbox" value="1" name="<?= $baseName ?>_edit" class="mis-perm mis-edit" <?= $editChecked ? 'checked' : '' ?>></td>
        <td class="perm-check"><input type="checkbox" value="1" name="<?= $baseName ?>_delete" class="mis-perm mis-delete" <?= $deleteChecked ? 'checked' : '' ?>></td>
        <td class="perm-check"><input type="checkbox" class="mis-perm mis-all"></td>
    </tr>
    <?php
}

/** View-only row for a module with no separate edit/delete action (e.g. a report or calculator). */
function renderPermViewOnlyRow(string $baseName, string $label, ?array $row = null): void
{
    $viewChecked = $row && (int) ($row[$baseName] ?? 0) === 1;
    $labelSafe = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    ?>
    <tr class="perm-row">
        <td class="perm-label"><?= $labelSafe ?></td>
        <td class="perm-check"><input type="checkbox" value="1" name="<?= $baseName ?>" class="mis-perm mis-view" <?= $viewChecked ? 'checked' : '' ?>></td>
        <td class="perm-check perm-na">—</td>
        <td class="perm-check perm-na">—</td>
        <td class="perm-check perm-na">—</td>
    </tr>
    <?php
}

/**
 * Row for a standalone "Add" permission that's deliberately separate from
 * its module's View/Edit/Delete (e.g. 'Add Input Stock' vs 'Manage Input
 * Stock') — kept out of the mis-view/mis-perm-group machinery so it's never
 * swept up by "Select All (View Only)" or a module's own Edit/Delete/All
 * toggle.
 *
 * $fieldName lets the submitted field differ from the admin_log column when
 * they intentionally don't match (e.g. 'Add Payment Entry' submits as
 * add_payment_entry but is stored in the payment_entry column).
 */
function renderPermAddOnlyRow(string $baseName, string $label, ?array $row = null, ?string $fieldName = null): void
{
    $checked = $row && (int) ($row[$baseName] ?? 0) === 1;
    $field = $fieldName ?? $baseName;
    $labelSafe = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    ?>
    <tr class="perm-row">
        <td class="perm-label"><?= $labelSafe ?> <span class="perm-add-tag">(Add)</span></td>
        <td class="perm-check"><input type="checkbox" value="1" name="<?= $field ?>" class="perm-add-only" <?= $checked ? 'checked' : '' ?>></td>
        <td class="perm-check perm-na">—</td>
        <td class="perm-check perm-na">—</td>
        <td class="perm-check perm-na">—</td>
    </tr>
    <?php
}

/** A bold divider row to break the long module list into readable sections. */
function renderPermSectionHeader(string $title): void
{
    ?>
    <tr class="perm-section-row"><td colspan="5"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php
}

/**
 * Emits the CSS + JS once per page for the permission table above: column
 * styling, plus the behavior wiring for every .mis-perm-group row — "All"
 * toggles View+Edit+Delete together, Edit/Delete each imply View (can't
 * manage what you can't see), and unchecking View clears Edit/Delete/All.
 * Scoped per-row via querySelector within that row so multiple module rows
 * on the same page don't interfere with each other.
 */
function renderPermGroupScript(): void
{
    ?>
    <style>
    .view-only-row { display:flex; justify-content:flex-end; margin-bottom:10px; }
    /* Matches this app's own purple "page-badge" pill (seen on CP Wallet Commission Calculator etc.) */
    .view-only-btn { background:#fff; color:#6b7280; border-color:#d1d5db; }
    .view-only-btn:hover { background:#f9fafb; color:#4b5563; border-color:#9ca3af; }
    .view-only-btn.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color:#fff; border-color:transparent;
        box-shadow: 0 4px 12px rgba(102,126,234,0.3);
    }
    .view-only-btn.active:hover { filter:brightness(1.05); }
    .view-only-btn.active::before { content:"\2713\00A0"; }
    #permGrid { width:100%; border-collapse:collapse; font-size:13.5px; }
    #permGrid th { text-align:left; padding:8px 10px; border-bottom:2px solid #dee2e6; font-size:12px; text-transform:uppercase; letter-spacing:.4px; color:#6b7280; }
    #permGrid th.perm-check-head { text-align:center; width:70px; }
    #permGrid th.perm-col-clickable { cursor:pointer; user-select:none; }
    #permGrid th.perm-col-clickable:hover { color:#6363ff; text-decoration:underline; }
    #permGrid td.perm-label { padding:7px 10px; white-space:nowrap; }
    #permGrid td.perm-check { padding:7px 6px; text-align:center; }
    #permGrid td.perm-na { color:#cbd5e1; }
    #permGrid tr.perm-row:hover { background:#f8fafc; }
    #permGrid tr.perm-section-row td { padding:12px 10px 6px; font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:.5px; color:#475569; border-bottom:1px solid #e5e7eb; }
    #permGrid .perm-add-tag { font-size:11px; color:#9ca3af; font-weight:400; }
    </style>
    <script>
    (function () {
        document.querySelectorAll('.mis-perm-group').forEach(function (group) {
            var view = group.querySelector('.mis-view');
            var edit = group.querySelector('.mis-edit');
            var del  = group.querySelector('.mis-delete');
            var all  = group.querySelector('.mis-all');
            if (!view || !edit || !del || !all) return;

            all.checked = view.checked && edit.checked && del.checked;

            all.addEventListener('change', function () {
                view.checked = edit.checked = del.checked = all.checked;
            });
            [edit, del].forEach(function (cb) {
                cb.addEventListener('change', function () {
                    if (cb.checked) view.checked = true;
                    if (!edit.checked || !del.checked) all.checked = false;
                });
            });
            view.addEventListener('change', function () {
                if (!view.checked) { edit.checked = del.checked = all.checked = false; }
            });
        });

        // Toggles one column (View/Edit/Delete/All) for every module row at
        // once — same cascade rules as a single row (e.g. turning View off
        // for everyone also clears their Edit/Delete/All), reused here by
        // dispatching a real 'change' event on each checkbox instead of
        // duplicating the cascade logic above. Shared by the column headers
        // and the "Select All (View Only)" button, so both behave identically.
        function togglePermColumn(boxClass, onToggle) {
            var boxes = Array.prototype.slice.call(document.querySelectorAll('#permGrid .' + boxClass));
            if (!boxes.length) return;
            var allChecked = boxes.every(function (cb) { return cb.checked; });
            var newState = !allChecked;
            boxes.forEach(function (cb) {
                if (cb.checked !== newState) {
                    cb.checked = newState;
                    cb.dispatchEvent(new Event('change'));
                }
            });
            if (onToggle) onToggle(newState);
        }

        function wirePermColumnHeader(headerId, boxClass) {
            var th = document.getElementById(headerId);
            if (!th) return;
            th.classList.add('perm-col-clickable');
            th.title = 'Click to toggle this column for every module';
            th.addEventListener('click', function () { togglePermColumn(boxClass); });
        }
        wirePermColumnHeader('permColView', 'mis-view');
        wirePermColumnHeader('permColEdit', 'mis-edit');
        wirePermColumnHeader('permColDelete', 'mis-delete');
        wirePermColumnHeader('permColAll', 'mis-all');

        // "Select All (View Only)" button — exactly the View column header's
        // toggle (checks/unchecks every View box), just as a labeled button
        // with a persistent purple look while the column is fully checked.
        var viewOnlyBtn = document.getElementById('viewOnlyBtn');
        if (viewOnlyBtn) {
            viewOnlyBtn.classList.toggle('active', document.querySelectorAll('#permGrid .mis-view').length > 0 &&
                Array.prototype.every.call(document.querySelectorAll('#permGrid .mis-view'), function (cb) { return cb.checked; }));
            viewOnlyBtn.addEventListener('click', function () {
                togglePermColumn('mis-view', function (newState) {
                    viewOnlyBtn.classList.toggle('active', newState);
                });
            });
        }
    })();
    </script>
    <?php
}
