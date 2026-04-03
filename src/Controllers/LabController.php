<?php
/**
 * XPLabs - Lab Controller
 * Handles lab floor and station management
 */

namespace XPLabs\Controllers;

use XPLabs\Core\Controller;
use XPLabs\Core\Response;
use XPLabs\Services\LabService;

class LabController extends Controller
{
    private LabService $labService;

    public function __construct()
    {
        parent::__construct();
        $this->labService = new LabService();
    }

    /**
     * Display seat plan view
     */
    public function seatPlan(): Response
    {
        $auth = $this->requireRole(['admin', 'teacher', 'student']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $floors = $this->labService->getFloors();
        $stations = $this->labService->getStations();
        $stats = $this->labService->getStats();

        // Group stations by floor
        $stationsByFloor = [];
        foreach ($stations as $s) {
            $fid = $s['floor_id'] ?? 0;
            if (!isset($stationsByFloor[$fid])) $stationsByFloor[$fid] = [];
            $stationsByFloor[$fid][] = $s;
        }

        $currentFloorId = $this->request->query('floor') ?? ($floors[0]['id'] ?? null);
        $currentFloorStations = $stationsByFloor[$currentFloorId] ?? [];
        $currentFloor = null;
        foreach ($floors as $f) {
            if ($f['id'] == $currentFloorId) {
                $currentFloor = $f;
                break;
            }
        }

        return $this->view('lab.seatplan', [
            'floors' => $floors,
            'stations' => $stations,
            'stats' => $stats,
            'stationsByFloor' => $stationsByFloor,
            'currentFloorId' => $currentFloorId,
            'currentFloorStations' => $currentFloorStations,
            'currentFloor' => $currentFloor,
        ]);
    }

    /**
     * Display monitoring view (computer cafe style)
     */
    public function monitoring(): Response
    {
        $auth = $this->requireRole(['admin', 'teacher']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $floors = $this->labService->getFloors();
        $stations = $this->labService->getStations();
        $stats = $this->labService->getStats();

        // Group stations by floor
        $stationsByFloor = [];
        foreach ($stations as $s) {
            $fid = $s['floor_id'] ?? 0;
            if (!isset($stationsByFloor[$fid])) $stationsByFloor[$fid] = [];
            $stationsByFloor[$fid][] = $s;
        }

        $currentFloorId = $this->request->query('floor') ?? ($floors[0]['id'] ?? null);
        $currentFloorStations = $stationsByFloor[$currentFloorId] ?? [];
        $currentFloor = null;
        foreach ($floors as $f) {
            if ($f['id'] == $currentFloorId) {
                $currentFloor = $f;
                break;
            }
        }

        return $this->view('lab.monitoring', [
            'floors' => $floors,
            'stations' => $stations,
            'stats' => $stats,
            'stationsByFloor' => $stationsByFloor,
            'currentFloorId' => $currentFloorId,
            'currentFloorStations' => $currentFloorStations,
            'currentFloor' => $currentFloor,
        ]);
    }

    /**
     * Get floor detail (API)
     */
    public function floorDetail(string $floorId): Response
    {
        $auth = $this->requireRole(['admin', 'teacher']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $floor = $this->labService->getFloorById((int) $floorId);
        if (!$floor) {
            return $this->error('Floor not found', 404);
        }

        $stations = $this->labService->getStations((int) $floorId);

        return $this->success([
            'floor' => $floor,
            'stations' => $stations,
        ]);
    }

    /**
     * Get station detail (API)
     */
    public function stationDetail(string $stationId): Response
    {
        $auth = $this->requireRole(['admin', 'teacher']);
        if ($auth instanceof Response) {
            return $auth;
        }

        $station = $this->labService->getStationById((int) $stationId);
        if (!$station) {
            return $this->error('Station not found', 404);
        }

        return $this->success(['station' => $station]);
    }
}