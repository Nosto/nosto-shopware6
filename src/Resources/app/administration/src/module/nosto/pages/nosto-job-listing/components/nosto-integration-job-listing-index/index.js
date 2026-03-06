/* eslint-disable sw-core-rules/require-package-annotation */
import template from './nosto-integration-job-listing-index.html.twig';
import { getJobStatusLabel, getJobStatusTone, isJobRunningStatus } from '../../job-status.helper';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

const CHILD_COUNT_KEYS = Object.freeze({
    TOTAL: 'total',
    SUCCESS: 'success',
    PENDING: 'pending',
    ERROR: 'error',
});

const MESSAGE_COUNT_KEYS = Object.freeze({
    TOTAL: 'total',
    INFO: 'info',
    WARNING: 'warning',
    ERROR: 'error',
});

function normalizeMessageType(type) {
    if (type === MESSAGE_COUNT_KEYS.INFO) {
        return MESSAGE_COUNT_KEYS.INFO;
    }

    if (type === MESSAGE_COUNT_KEYS.WARNING) {
        return MESSAGE_COUNT_KEYS.WARNING;
    }

    if (type === MESSAGE_COUNT_KEYS.ERROR) {
        return MESSAGE_COUNT_KEYS.ERROR;
    }

    return MESSAGE_COUNT_KEYS.TOTAL;
}

function sortJobMessages(messages) {
    return [...messages].sort((a, b) => {
        if (a.createdAt > b.createdAt) {
            return 1;
        }

        if (a.createdAt < b.createdAt) {
            return -1;
        }

        return 0;
    });
}

Component.extend('nosto-integration-job-listing-index', 'nosto-job-listing-index', {
    template,

    inject: [
        'NostoRescheduleService',
        'repositoryFactory',
        'filterFactory',
        'feature',
    ],

    methods: {
        updateList(filterCriteria) {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addFilter(Criteria.equals('parentId', null));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC', false));

            if (filterCriteria) {
                filterCriteria.forEach((filter) => {
                    criteria.addFilter(filter);
                });
            }

            if (Array.isArray(this.jobTypes) && this.jobTypes.length > 0) {
                criteria.addFilter(Criteria.equalsAny('type', this.jobTypes));
            }

            return this.jobRepository.search(criteria, Shopware.Context.api).then((jobItems) => {
                this.jobItems = jobItems;
                this.$emit('job-list-meta-loaded', this.extractFilterMeta(jobItems));
            });
        },

        extractFilterMeta(jobItems) {
            const statuses = [...new Set(jobItems.map((item) => item.status).filter((status) => !!status))];
            const types = [...new Set(jobItems.map((item) => item.name).filter((name) => !!name))];

            return {
                statuses,
                types,
            };
        },

        getJobCounts(job) {
            const extensionCounts = job?.extensions?.jobCounts
                ?? job?.jobCounts
                ?? {};
            const childJobs = extensionCounts.childJobs ?? {};
            const messages = extensionCounts.messages ?? {};

            return {
                childJobs: {
                    [CHILD_COUNT_KEYS.TOTAL]: Number(childJobs[CHILD_COUNT_KEYS.TOTAL] ?? 0),
                    [CHILD_COUNT_KEYS.SUCCESS]: Number(childJobs[CHILD_COUNT_KEYS.SUCCESS] ?? 0),
                    [CHILD_COUNT_KEYS.PENDING]: Number(childJobs[CHILD_COUNT_KEYS.PENDING] ?? 0),
                    [CHILD_COUNT_KEYS.ERROR]: Number(childJobs[CHILD_COUNT_KEYS.ERROR] ?? 0),
                },
                messages: {
                    [MESSAGE_COUNT_KEYS.TOTAL]: Number(messages[MESSAGE_COUNT_KEYS.TOTAL] ?? 0),
                    [MESSAGE_COUNT_KEYS.INFO]: Number(messages[MESSAGE_COUNT_KEYS.INFO] ?? 0),
                    [MESSAGE_COUNT_KEYS.WARNING]: Number(messages[MESSAGE_COUNT_KEYS.WARNING] ?? 0),
                    [MESSAGE_COUNT_KEYS.ERROR]: Number(messages[MESSAGE_COUNT_KEYS.ERROR] ?? 0),
                },
            };
        },

        getChildCountByType(job, type) {
            const data = this.getJobCounts(job).childJobs;

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

        getMessageCountByType(job, type) {
            const normalizedType = normalizeMessageType(type);

            return Number(this.getJobCounts(job).messages?.[normalizedType] ?? 0);
        },

        getMessagesCount(job, type) {
            return this.getMessageCountByType(job, type);
        },

        getMessagesTotalCount(job) {
            return this.getMessageCountByType(job, MESSAGE_COUNT_KEYS.TOTAL);
        },

        showJobMessages(job) {
            if (!job?.id) {
                return;
            }

            const criteria = new Criteria(1, 500);
            criteria.addFilter(Criteria.equals('jobId', job.id));
            criteria.addSorting(Criteria.sort('createdAt', 'ASC', false));

            this.currentJobMessages = [];
            this.showMessagesModal = true;

            this.messageRepository.search(criteria, Shopware.Context.api).then((messages) => {
                this.currentJobMessages = sortJobMessages(messages);
            }).catch(() => {
                this.currentJobMessages = [];
            });
        },

        getStatusTone(status) {
            return getJobStatusTone(status);
        },

        getStatusLabel(status) {
            return getJobStatusLabel(status, (key) => this.$tc(key));
        },

        isRunningStatus(status) {
            return isJobRunningStatus(status);
        },
    },
});
