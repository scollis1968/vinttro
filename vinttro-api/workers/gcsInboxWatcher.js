// vinttro-api/workers/gcsInboxWatcher.js
const fs = require('fs');
const path = require('path');
const processor = require('../services/acturisStreamProcessor');

const BASE_DIR = process.env.ACTURIS_MOUNT_PATH || '/data/acturis';
const INBOX_DIR = path.join(BASE_DIR, 'inbox');
const PROCESSING_DIR = path.join(BASE_DIR, 'processing');
const COMPLETED_DIR = path.join(BASE_DIR, 'completed');
const FAILED_DIR = path.join(BASE_DIR, 'failed');

// Ensure directories exist
[INBOX_DIR, PROCESSING_DIR, COMPLETED_DIR, FAILED_DIR].forEach(dir => {
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
});

// Self-healing: Recover any files orphaned in 'processing' by a previous crash
function recoverOrphanedFiles() {
    try {
        const stuckFiles = fs.readdirSync(PROCESSING_DIR).filter(f => f.toLowerCase().endsWith('.csv'));
        for (const file of stuckFiles) {
            console.log(`[GCS Watcher] ⚠️ Found orphaned file in processing: ${file}. Returning to inbox...`);
            fs.renameSync(path.join(PROCESSING_DIR, file), path.join(INBOX_DIR, file));
        }
    } catch (err) {
        console.error('[GCS Watcher] Error during recovery check:', err.message);
    }
}

let isRunning = false;

async function checkInbox() {
    if (isRunning) return;
    isRunning = true;

    try {
        const files = fs.readdirSync(INBOX_DIR).filter(f => f.toLowerCase().endsWith('.csv'));

        for (const fileName of files) {
            const inboxPath = path.join(INBOX_DIR, fileName);
            const processingPath = path.join(PROCESSING_DIR, fileName);

            console.log(`\n======================================================`);
            console.log(`[GCS Watcher] 📥 Discovered file: ${fileName}`);

            // Move to processing
            fs.renameSync(inboxPath, processingPath);
            console.log(`[GCS Watcher] 🔒 Moved ${fileName} to processing/`);

            const startTime = Date.now();
            try {
                const stats = await processor.processFile(processingPath);
                const duration = ((Date.now() - startTime) / 1000).toFixed(1);

                console.log(`[GCS Watcher] ✅ Finished ${fileName} in ${duration}s:`, stats);

                const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
                fs.renameSync(processingPath, path.join(COMPLETED_DIR, `${timestamp}_${fileName}`));
            } catch (fileErr) {
                console.error(`[GCS Watcher] ❌ Failed to process ${fileName}:`, fileErr);

                fs.renameSync(processingPath, path.join(FAILED_DIR, fileName));
                fs.writeFileSync(
                    path.join(FAILED_DIR, `${fileName}.error.log`),
                    `Error at ${new Date().toISOString()}:\n${fileErr.stack || fileErr.message}`
                );
            }
        }
    } catch (scanErr) {
        console.error('[GCS Watcher] Scan error:', scanErr);
    } finally {
        isRunning = false;
    }
}

const POLL_INTERVAL_MS = parseInt(process.env.POLL_INTERVAL_MS, 10) || 3000;
console.log(`🚀 Acturis GCS Watcher started. Monitoring: ${INBOX_DIR}`);
console.log(`Checking every ${POLL_INTERVAL_MS / 1000} seconds...`);

// Run recovery first, then start loop
recoverOrphanedFiles();
setInterval(checkInbox, POLL_INTERVAL_MS);
checkInbox();