/**
 * @sw-package discovery
 */

const STATUS_TONES = Object.freeze({
    SUCCESS: 'success',
    DANGER: 'danger',
    INFO: 'info',
    NEUTRAL: 'neutral',
});

const SUCCESS_STATUSES = Object.freeze(['success', 'succeed', 'finished', 'done', 'completed', 'running']);
const ERROR_STATUSES = Object.freeze(['error', 'failed']);
const INFO_STATUSES = Object.freeze(['in_progress', 'processing']);

/**
 * @private
 */
export function isJobRunningStatus(status) {
    return (status ?? '').toLowerCase() === 'running';
}

/**
 * @private
 */
export function getJobStatusTone(status) {
    const value = (status ?? '').toLowerCase();

    if (ERROR_STATUSES.includes(value)) {
        return STATUS_TONES.DANGER;
    }

    if (SUCCESS_STATUSES.includes(value)) {
        return STATUS_TONES.SUCCESS;
    }

    if (INFO_STATUSES.includes(value)) {
        return STATUS_TONES.INFO;
    }

    return STATUS_TONES.NEUTRAL;
}

/**
 * @private
 */
export function getJobStatusLabel(status, translate) {
    const statusValue = String(status ?? '').trim();
    if (!statusValue) {
        return '';
    }

    const key = `job-listing.page.listing.grid.job-status.${statusValue}`;
    const translated = typeof translate === 'function' ? translate(key) : '';

    if (!translated || translated === key) {
        return statusValue.replace(/_/g, ' ');
    }

    return translated;
}
