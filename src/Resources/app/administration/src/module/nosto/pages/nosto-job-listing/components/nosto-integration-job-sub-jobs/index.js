/* eslint-disable sw-core-rules/require-package-annotation */
import template from './nosto-integration-job-sub-jobs.html.twig';
import { getJobStatusLabel, getJobStatusTone, isJobRunningStatus } from '../../job-status.helper';

const { Component } = Shopware;

Component.extend('nosto-integration-job-sub-jobs', 'nosto-job-sub-jobs', {
    template,

    methods: {
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
