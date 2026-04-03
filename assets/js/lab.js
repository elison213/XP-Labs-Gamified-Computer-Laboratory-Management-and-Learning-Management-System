/**
 * XPLabs - Lab Floor Plan Module
 * Handles rendering, real-time updates, and station interactions.
 */
(function () {
  'use strict';

  /**
   * LabFloorPlan - Renders and manages the lab floor plan
   */
  class LabFloorPlan {
    constructor(options) {
      this.gridEl = document.getElementById(options.gridId || 'seat-grid');
      this.floorId = options.floorId || null;
      this.apiUrl = options.apiUrl || '/api/lab/stations';
      this.pollInterval = options.pollInterval || 5000; // 5 seconds
      this.pollTimer = null;
      this.stations = [];
    }

    /**
     * Initialize the floor plan
     */
    init() {
      this.bindEvents();
      this.startPolling();
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
      // Seat click → show detail modal
      if (this.gridEl) {
        this.gridEl.addEventListener('click', (e) => {
          const seat = e.target.closest('.pc-seat[data-status="active"], .pc-seat[data-status="idle"]');
          if (seat) {
            this.showSeatDetail(seat);
          }
        });
      }

      // Column density toggle
      document.querySelectorAll('[data-cols]').forEach((btn) => {
        btn.addEventListener('click', () => {
          document.querySelectorAll('[data-cols]').forEach((b) => b.classList.remove('active'));
          btn.classList.add('active');
          if (this.gridEl) {
            this.gridEl.setAttribute('data-cols', btn.getAttribute('data-cols'));
          }
        });
      });

      // Status filter
      document.querySelectorAll('[data-filter-status]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const status = btn.getAttribute('data-filter-status');
          document.querySelectorAll('[data-filter-status]').forEach((b) => b.classList.remove('active'));
          btn.classList.add('active');
          this.filterByStatus(status);
        });
      });

      // Refresh button
      const refreshBtn = document.getElementById('btn-refresh-seatplan');
      if (refreshBtn) {
        refreshBtn.addEventListener('click', () => this.refresh());
      }
    }

    /**
     * Fetch stations from API
     */
    async fetchStations() {
      try {
        const url = this.floorId
          ? `${this.apiUrl}?floor_id=${this.floorId}`
          : this.apiUrl;
        const response = await fetch(url);
        if (!response.ok) throw new Error('Failed to fetch stations');
        const data = await response.json();
        this.stations = data.stations || data;
        this.renderStations(this.stations);
        this.updateStats(this.stations);
      } catch (err) {
        console.warn('LabFloorPlan: Using static data (API not available)', err);
      }
    }

    /**
     * Render stations into the grid
     */
    renderStations(stations) {
      if (!this.gridEl) return;

      // If we have dynamic data, rebuild the grid
      if (stations && stations.length > 0) {
        const statusMeta = {
          active: { label: 'Active', badge: 'text-bg-success', dot: 'seat-dot-active' },
          idle: { label: 'Idle', badge: 'text-bg-warning text-dark', dot: 'seat-dot-idle' },
          offline: { label: 'Offline', badge: 'text-bg-secondary', dot: 'seat-dot-offline' },
          maintenance: { label: 'Maintenance', badge: 'text-bg-danger', dot: 'seat-dot-maint' },
        };

        this.gridEl.innerHTML = stations.map((s) => {
          const st = s.status || 'offline';
          const meta = statusMeta[st] || statusMeta.offline;
          const initial = s.user ? s.user.charAt(0).toUpperCase() : '–';
          const displayName = s.user || (st === 'maintenance' ? 'Maintenance' : 'Available');
          const isClickable = ['active', 'idle'].includes(st);

          return `
            <div class="pc-seat pc-seat--${st} ${isClickable ? 'pc-seat--clickable' : ''}"
                 data-station-id="${s.id}"
                 data-status="${st}"
                 data-user="${s.user || ''}"
                 data-task="${s.task || ''}"
                 data-since="${s.since || ''}"
                 tabindex="0"
                 role="${isClickable ? 'button' : 'img'}"
                 aria-label="${s.station_code || s.id} — ${meta.label}${s.user ? ' — ' + s.user : ''}">
              <div class="pc-seat__header">
                <span class="pc-seat__id">${s.station_code || s.id}</span>
                <span class="seat-dot ${meta.dot} ${st === 'active' ? 'seat-dot-pulse' : ''}"></span>
              </div>
              <div class="pc-seat__monitor" aria-hidden="true">🖥</div>
              <div class="pc-seat__avatar pc-avatar--${st}">${initial}</div>
              <div class="pc-seat__name" title="${displayName}">${displayName}</div>
              <div class="pc-seat__since">${s.since || (st === 'maintenance' ? '⚠ Maint.' : '—')}</div>
              <span class="badge ${meta.badge} pc-seat__badge mt-1">${meta.label}</span>
            </div>
          `;
        }).join('');
      }
    }

    /**
     * Update stat counters
     */
    updateStats(stations) {
      const counts = { active: 0, idle: 0, offline: 0, maintenance: 0 };
      stations.forEach((s) => {
        if (counts[s.status] !== undefined) counts[s.status]++;
      });

      // Update stat cards if they exist
      const statMap = {
        active: '.text-success.h3',
        idle: '.text-warning.h3',
        offline: '.text-secondary.h3',
        maintenance: '.text-danger.h3',
      };

      Object.entries(statMap).forEach(([status, selector]) => {
        const el = document.querySelector(`.col-6:has(${selector}) .h3, .col-6:has(.text-${status === 'maintenance' ? 'danger' : status === 'offline' ? 'secondary' : status === 'idle' ? 'warning' : 'success'}) .h3`);
        // Simpler approach: find by parent card
      });
    }

    /**
     * Filter seats by status
     */
    filterByStatus(status) {
      document.querySelectorAll('.pc-seat').forEach((seat) => {
        const match = status === 'all' || seat.getAttribute('data-status') === status;
        seat.style.opacity = match ? '1' : '0.2';
        seat.style.pointerEvents = match ? '' : 'none';
        seat.style.transform = match ? '' : 'scale(0.95)';
      });
    }

    /**
     * Show seat detail modal
     */
    showSeatDetail(seatEl) {
      const modal = document.getElementById('modalSeatDetail');
      if (!modal) return;

      const instance = bootstrap.Modal.getOrCreateInstance(modal);
      modal.querySelector('#modal-seat-id').textContent = seatEl.getAttribute('data-station-id') || '—';
      modal.querySelector('#modal-seat-user').textContent = seatEl.getAttribute('data-user') || '—';
      modal.querySelector('#modal-seat-task').textContent = seatEl.getAttribute('data-task') || '—';
      modal.querySelector('#modal-seat-since').textContent = seatEl.getAttribute('data-since') || '—';
      modal.querySelector('#modal-seat-status').textContent = seatEl.getAttribute('data-status') || '—';
      instance.show();
    }

    /**
     * Refresh data from API
     */
    async refresh() {
      const btn = document.getElementById('btn-refresh-seatplan');
      if (btn) {
        const orig = btn.innerHTML;
        btn.innerHTML = '↻ Refreshing…';
        btn.disabled = true;
        await this.fetchStations();
        btn.innerHTML = orig;
        btn.disabled = false;
      } else {
        await this.fetchStations();
      }
    }

    /**
     * Start polling for updates
     */
    startPolling() {
      this.stopPolling();
      this.pollTimer = setInterval(() => this.fetchStations(), this.pollInterval);
    }

    /**
     * Stop polling
     */
    stopPolling() {
      if (this.pollTimer) {
        clearInterval(this.pollTimer);
        this.pollTimer = null;
      }
    }

    /**
     * Update a single station's status
     */
    updateStation(stationId, updates) {
      const seat = this.gridEl?.querySelector(`[data-station-id="${stationId}"]`);
      if (!seat) return;

      Object.entries(updates).forEach(([key, value]) => {
        if (value !== undefined) {
          seat.setAttribute(`data-${key}`, value);
        }
      });

      // Re-render if status changed
      if (updates.status) {
        this.fetchStations();
      }
    }
  }

  // Expose globally
  window.XPLabs = window.XPLabs || {};
  window.XPLabs.LabFloorPlan = LabFloorPlan;

  // Auto-init if element exists
  document.addEventListener('DOMContentLoaded', () => {
    const gridEl = document.getElementById('seat-grid');
    if (gridEl) {
      const floorPlan = new LabFloorPlan({ gridId: 'seat-grid' });
      floorPlan.init();
      window.XPLabs.labFloorPlan = floorPlan;
    }
  });
})();