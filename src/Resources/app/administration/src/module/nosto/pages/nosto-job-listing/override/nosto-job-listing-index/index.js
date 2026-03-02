/* eslint-disable sw-core-rules/require-package-annotation */
import template from './nosto-job-listing-index.html.twig';
import { getJobStatusLabel, getJobStatusTone } from '../job-status.helper';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

const CHILD_COUNT_KEYS = Object.freeze({
    TOTAL: 'total',
    SUCCESS: 'success',
    PENDING: 'pending',
    ERROR: 'error',
});

function sortJobMessages(jobs) {
    jobs.forEach((job) => {
        if (!Array.isArray(job.messages)) {
            job.messages = [];
            return;
        }

        job.messages = job.messages.sort((a, b) => {
            if (a.createdAt > b.createdAt) {
                return 1;
            }

            if (a.createdAt < b.createdAt) {
                return -1;
            }

            return 0;
        });
    });

    return jobs;
}

function normalizeChildCounts(rows = []) {
    const normalized = {};

    rows.forEach((row) => {
        if (!row?.parentJobId) {
            return;
        }

        normalized[row.parentJobId] = {
            [CHILD_COUNT_KEYS.TOTAL]: Number(row?.childJobs?.total ?? 0),
            [CHILD_COUNT_KEYS.SUCCESS]: Number(row?.childJobs?.byStatus?.success ?? 0),
            [CHILD_COUNT_KEYS.PENDING]: Number(row?.childJobs?.byStatus?.pending ?? 0),
            [CHILD_COUNT_KEYS.ERROR]: Number(row?.childJobs?.byStatus?.error ?? 0),
        };
    });

    return normalized;
}

Component.override('nosto-job-listing-index', {
    template,

    inject: [
        'NostoRescheduleService',
        'NostoIntegrationProviderService',
        'repositoryFactory',
        'filterFactory',
        'feature',
    ],

    data() {
        return {
            childJobCounts: {},
        };
    },

    methods: {
        updateList(filterCriteria) {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addFilter(Criteria.equals('parentId', null));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC', false));
            criteria.addAssociation('messages');

            if (filterCriteria) {
                filterCriteria.forEach((filter) => {
                    criteria.addFilter(filter);
                });
            }

            if (this.jobTypes !== []) {
                criteria.addFilter(Criteria.equalsAny('type', this.jobTypes));
            }

            return this.jobRepository.search(criteria, Shopware.Context.api).then((jobItems) => {
                this.jobItems = sortJobMessages(jobItems);
                return this.loadChildCounts(jobItems.map((job) => job.id));
            });
        },

        loadChildCounts(parentJobIds) {
            this.childJobCounts = {};

            return this.NostoIntegrationProviderService.getJobChildCount(parentJobIds).then((response) => {
                const rows = Array.isArray(response?.data) ? response.data : [];
                this.childJobCounts = normalizeChildCounts(rows);
            }).catch(() => {
                this.childJobCounts = {};
            });
        },

        getChildCountByType(job, type) {
            const data = this.childJobCounts[job.id];
            if (typeof data === 'number') {
                return type === CHILD_COUNT_KEYS.TOTAL ? Number(data) : 0;
            }

            return Number(data?.[type] ?? 0);
        },

        getChildrenCount(job) {
            return this.getChildCountByType(job, CHILD_COUNT_KEYS.TOTAL);
        },

        getChildrenSuccessCount(job) {
            return this.getChildCountByType(job, CHILD_COUNT_KEYS.SUCCESS);
        },

        getChildrenPendingCount(job) {
            return this.getChildCountByType(job, CHILD_COUNT_KEYS.PENDING);
        },

        getChildrenErrorCount(job) {
            return this.getChildCountByType(job, CHILD_COUNT_KEYS.ERROR);
        },

        getStatusTone(status) {
            return getJobStatusTone(status);
        },

        getStatusLabel(status) {
            return getJobStatusLabel(status, (key) => this.$tc(key));
        },

    },
});
