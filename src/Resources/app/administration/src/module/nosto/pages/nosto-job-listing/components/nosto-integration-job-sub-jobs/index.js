/* eslint-disable sw-core-rules/require-package-annotation */
import template from './nosto-integration-job-sub-jobs.html.twig';
import { getJobStatusLabel, getJobStatusTone, isJobRunningStatus } from '../../job-status.helper';
import { fetchJobMessages } from '../../utils/job-messages.helper';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

const MESSAGE_COUNT_KEYS = Object.freeze({
    TOTAL: 'total',
    INFO: 'info',
    WARNING: 'warning',
    ERROR: 'error',
});

Component.extend('nosto-integration-job-sub-jobs', 'nosto-job-sub-jobs', {
    template,

    computed: {
        messageRepository() {
            return this.repositoryFactory.create('nosto_scheduler_job_message');
        },
    },

    methods: {
        initModalData() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addFilter(Criteria.equals('parentId', this.jobId));
            criteria.addSorting(Criteria.sort('createdAt', 'DESC', false));

            this.jobRepository.search(criteria, Shopware.Context.api).then((jobItems) => {
                this.subJobs = jobItems;
            });
        },

        getMessageCounts(job) {
            return job?.extensions?.jobCounts?.messages ?? job?.jobCounts?.messages ?? {};
        },

        getMessagesCount(job, type) {
            const counts = this.getMessageCounts(job);
            const key = type === MESSAGE_COUNT_KEYS.INFO
                ? MESSAGE_COUNT_KEYS.INFO
                : (type === MESSAGE_COUNT_KEYS.WARNING ? MESSAGE_COUNT_KEYS.WARNING : MESSAGE_COUNT_KEYS.ERROR);

            return Number(counts[key] ?? 0);
        },

        getMessagesTotalCount(job) {
            const counts = this.getMessageCounts(job);

            return Number(counts[MESSAGE_COUNT_KEYS.TOTAL] ?? 0);
        },

        showMessageModal(job) {
            const jobId = job?.id;
            if (!jobId) {
                return;
            }

            this.currentJobMessages = [];
            this.showMessagesModal = true;

            const expectedTotal = this.getMessagesTotalCount(job);
            fetchJobMessages({
                messageRepository: this.messageRepository,
                jobId,
                expectedTotal,
            }).then((messages) => {
                this.currentJobMessages = messages;
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
