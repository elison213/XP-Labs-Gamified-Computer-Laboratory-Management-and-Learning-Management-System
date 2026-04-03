/**
 * XPLabs — API & app config (point apiBaseUrl at your REST server when ready).
 * Access: window.XPLabs.config
 */
(function (global) {
  'use strict';

  var origin = '';
  try {
    origin = global.location && global.location.origin ? global.location.origin : '';
  } catch (e) {}

  global.XPLabs = global.XPLabs || {};

  global.XPLabs.config = {
    /** Base URL for REST API (no trailing slash). Example: origin + '/api/v1' */
    apiBaseUrl: origin ? origin + '/api/v1' : '/api/v1',

    /**
     * Named routes — use with XPLabs.api.url('assignments') or XPLabs.api.get(XPLabs.config.endpoints.assignments.list)
     * Adjust paths to match your backend.
     */
    endpoints: {
      auth: { login: '/auth/login', logout: '/auth/logout', me: '/auth/me' },
      attendance: { qr: '/attendance/qr', history: '/attendance/history', sessions: '/attendance/sessions' },
      assignments: { list: '/assignments', one: '/assignments/:id', submit: '/assignments/:id/submissions' },
      submissions: { list: '/submissions' },
      leaderboard: { list: '/leaderboard' },
      users: { list: '/users', one: '/users/:id', importCsv: '/users/import' },
      logs: { list: '/logs' },
      system: { settings: '/system/settings' },
      monitoring: { live: '/monitoring/live' },
      announcements: { list: '/announcements', one: '/announcements/:id' },
      notifications: { list: '/notifications' },
      activity: { feed: '/activity' },
      profile: { me: '/profile' }
    }
  };
})(typeof window !== 'undefined' ? window : this);
