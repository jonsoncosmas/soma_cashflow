/**
 * Soma Cashflow - Offline sync (Phase 9)
 *
 * Wraps a transaction form so that:
 *  - online: submits via fetch() to the idempotent API endpoint, reloads on success
 *  - offline / network failure: queues the entry in IndexedDB with a
 *    client-generated UUID, shows a "saved offline" message, clears the
 *    form for the next entry
 *  - on page load and on the browser's 'online' event: flushes anything
 *    still queued, removing each entry from IndexedDB only after the
 *    server confirms it (so a sync that's interrupted partway through
 *    never loses or duplicates an entry)
 */
(function () {
    const DB_NAME = 'soma_cashflow_offline';
    const STORE_NAME = 'pending_transactions';
    const DB_VERSION = 1;

    function openDb() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    db.createObjectStore(STORE_NAME, { keyPath: 'uuid' });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    async function queueEntry(entry) {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_NAME, 'readwrite');
            tx.objectStore(STORE_NAME).put(entry);
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    }

    async function getAllQueued() {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_NAME, 'readonly');
            const req = tx.objectStore(STORE_NAME).getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    async function removeQueued(uuid) {
        const db = await openDb();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_NAME, 'readwrite');
            tx.objectStore(STORE_NAME).delete(uuid);
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    }

    function endpointFor(context) {
        return context === 'personal'
            ? '/soma_cashflow/public/api_personal_transaction.php'
            : '/soma_cashflow/public/api_transaction.php';
    }

    async function sendToServer(entry) {
        const res = await fetch(endpointFor(entry.context), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(entry.payload),
        });
        if (!res.ok) {
            throw new Error('Server rejected the request (status ' + res.status + ')');
        }
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.error || 'Unknown server error');
        }
        return data;
    }

    /** Attempts to flush every queued entry. Safe to call repeatedly/concurrently. */
    async function flushQueue(statusEl) {
        let queued;
        try {
            queued = await getAllQueued();
        } catch (e) {
            return; // IndexedDB unavailable - nothing we can do
        }
        if (queued.length === 0) return;

        if (statusEl) statusEl.textContent = 'Syncing ' + queued.length + ' saved offline…';

        for (const entry of queued) {
            try {
                await sendToServer(entry);
                await removeQueued(entry.uuid); // only purge AFTER server confirms
            } catch (e) {
                // Still offline or server still unreachable - leave it queued, try again later.
                if (statusEl) statusEl.textContent = 'Still offline - ' + queued.length + ' entr' + (queued.length === 1 ? 'y' : 'ies') + ' waiting to sync.';
                return;
            }
        }
        if (statusEl) {
            statusEl.textContent = 'All offline entries synced.';
            setTimeout(() => { statusEl.textContent = ''; }, 4000);
        }
    }

    /**
     * Wires a transaction form for offline-capable submission.
     * context: 'business' or 'personal'. extraFields: object merged into
     * every payload (e.g. {business_id: 5}).
     */
    function wireOfflineForm(formEl, context, extraFields, statusEl) {
        formEl.addEventListener('submit', async function (e) {
            e.preventDefault();

            const uuid = crypto.randomUUID();
            const payload = Object.assign({
                uuid: uuid,
                type: formEl.querySelector('[name=type]').value,
                category: formEl.querySelector('[name=category]').value,
                amount: formEl.querySelector('[name=amount]').value,
                description: formEl.querySelector('[name=description]') ? formEl.querySelector('[name=description]').value : '',
                transaction_date: formEl.querySelector('[name=transaction_date]').value,
            }, extraFields || {});

            const entry = { uuid: uuid, context: context, payload: payload, queuedAt: Date.now() };

            if (statusEl) statusEl.textContent = 'Saving…';

            try {
                await sendToServer(entry);
                if (statusEl) statusEl.textContent = 'Saved.';
                window.location.reload();
            } catch (networkOrServerError) {
                // Could be offline, or the server briefly unreachable - either way, don't lose the entry.
                try {
                    await queueEntry(entry);
                    if (statusEl) {
                        statusEl.textContent = '📴 Saved offline - will sync automatically when you\'re back online.';
                    }
                    formEl.reset();
                } catch (dbError) {
                    if (statusEl) {
                        statusEl.textContent = '⚠️ Could not save (offline storage unavailable in this browser). Please try again when online.';
                    }
                }
            }
        });
    }

    window.SomaOfflineSync = {
        wireOfflineForm: wireOfflineForm,
        flushQueue: flushQueue,
        getAllQueued: getAllQueued,
    };

    // Try to flush on load and whenever the browser regains connectivity.
    window.addEventListener('online', () => flushQueue(document.getElementById('offline-sync-status')));
    document.addEventListener('DOMContentLoaded', () => flushQueue(document.getElementById('offline-sync-status')));
})();
