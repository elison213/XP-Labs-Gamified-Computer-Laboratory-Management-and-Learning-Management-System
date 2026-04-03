/**
 * XPLabs — thin REST client (fetch + JSON). Integrate real backend by setting XPLabs.config.apiBaseUrl.
 */
(function (global) {
  'use strict';

  global.XPLabs = global.XPLabs || {};
  var cfg = function () {
    return global.XPLabs.config || { apiBaseUrl: '/api/v1', endpoints: {} };
  };

  function joinUrl(base, path) {
    var b = String(base || '').replace(/\/$/, '');
    var p = String(path || '');
    if (!p.startsWith('/')) p = '/' + p;
    return b + p;
  }

  /** Replace :id tokens in a path. */
  function interpolate(path, params) {
    if (!params) return path;
    return String(path).replace(/:([a-zA-Z]+)/g, function (_, key) {
      return params[key] != null ? encodeURIComponent(params[key]) : ':' + key;
    });
  }

  /** Build full URL under apiBaseUrl. Pass path like '/assignments' or '/assignments/:id' + { id: 3 } */
  function url(path, params) {
    var c = cfg();
    var p = interpolate(path || '', params || {});
    if (/^https?:\/\//i.test(p)) return p;
    if (p.charAt(0) !== '/') p = '/' + p;
    return joinUrl(c.apiBaseUrl, p);
  }

  async function request(path, options) {
    var opts = options || {};
    var headers = Object.assign(
      { Accept: 'application/json' },
      opts.headers || {}
    );
    if (opts.body != null && !(opts.body instanceof FormData) && typeof opts.body === 'object') {
      headers['Content-Type'] = 'application/json';
      opts = Object.assign({}, opts, { body: JSON.stringify(opts.body) });
    }
    var res = await fetch(path, Object.assign({}, opts, { headers: headers, credentials: 'same-origin' }));
    var ct = res.headers.get('Content-Type') || '';
    var data = ct.indexOf('application/json') !== -1 ? await res.json().catch(function () { return null; }) : await res.text();
    if (!res.ok) {
      var err = new Error((data && data.message) || res.statusText || 'Request failed');
      err.status = res.status;
      err.body = data;
      throw err;
    }
    return data;
  }

  global.XPLabs.api = {
    url: url,
    joinUrl: joinUrl,
    interpolate: interpolate,

    get: function (path, options) {
      return request(path, Object.assign({}, options, { method: 'GET' }));
    },

    post: function (path, body, options) {
      return request(path, Object.assign({}, options, { method: 'POST', body: body }));
    },

    patch: function (path, body, options) {
      return request(path, Object.assign({}, options, { method: 'PATCH', body: body }));
    },

    put: function (path, body, options) {
      return request(path, Object.assign({}, options, { method: 'PUT', body: body }));
    },

    delete: function (path, options) {
      return request(path, Object.assign({}, options, { method: 'DELETE' }));
    }
  };
})(typeof window !== 'undefined' ? window : this);
