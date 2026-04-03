<?php
/**
 * XPLabs - Event Dispatcher
 * Handles event registration and dispatching
 */

namespace XPLabs\Core;

class EventDispatcher
{
    private array $listeners = [];
    private array $wildcards = [];

    /**
     * Register an event listener
     */
    public function listen(string $event, callable|string $listener, int $priority = 0): void
    {
        if (str_contains($event, '*')) {
            $this->wildcards[$event][] = ['listener' => $listener, 'priority' => $priority];
        } else {
            $this->listeners[$event][] = ['listener' => $listener, 'priority' => $priority];
        }
    }

    /**
     * Register multiple listeners
     */
    public function listenMany(array $listeners): void
    {
        foreach ($listeners as $event => $listener) {
            if (is_array($listener)) {
                foreach ($listener as $l) {
                    $this->listen($event, $l);
                }
            } else {
                $this->listen($event, $listener);
            }
        }
    }

    /**
     * Dispatch an event
     */
    public function dispatch(Event $event): Event
    {
        $listeners = $this->getListeners($event->name());

        // Sort by priority
        usort($listeners, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($listeners as $handler) {
            $listener = $handler['listener'];
            
            // Call the listener
            if (is_callable($listener)) {
                $listener($event);
            } elseif (is_string($listener)) {
                // Handle class@method syntax
                if (str_contains($listener, '@')) {
                    [$class, $method] = explode('@', $listener, 2);
                    if (class_exists($class)) {
                        $instance = new $class();
                        $instance->$method($event);
                    }
                } elseif (class_exists($listener)) {
                    $instance = new $listener();
                    if (method_exists($instance, 'handle')) {
                        $instance->handle($event);
                    }
                }
            }

            if ($event->isPropagationStopped()) {
                break;
            }
        }

        return $event;
    }

    /**
     * Dispatch an event and halt after first non-null response
     */
    public function until(Event $event): mixed
    {
        $listeners = $this->getListeners($event->name());

        foreach ($listeners as $handler) {
            $listener = $handler['listener'];
            
            if (is_callable($listener)) {
                $response = $listener($event);
                if ($response !== null) {
                    return $response;
                }
            }

            if ($event->isPropagationStopped()) {
                break;
            }
        }

        return null;
    }

    /**
     * Get all listeners for an event
     */
    public function getListeners(string $eventName): array
    {
        $listeners = $this->listeners[$eventName] ?? [];

        // Check wildcard listeners
        foreach ($this->wildcards as $pattern => $wildcardListeners) {
            if ($this->eventMatches($pattern, $eventName)) {
                $listeners = array_merge($listeners, $wildcardListeners);
            }
        }

        return $listeners;
    }

    /**
     * Check if event matches wildcard pattern
     */
    private function eventMatches(string $pattern, string $event): bool
    {
        $pattern = str_replace(['\\*', '\\'], ['.*', '\\\\'], preg_quote($pattern, '/'));
        return (bool) preg_match('/^' . $pattern . '$/i', $event);
    }

    /**
     * Remove a listener
     */
    public function forget(string $event, ?callable $listener = null): void
    {
        if ($listener === null) {
            unset($this->listeners[$event]);
            return;
        }

        if (isset($this->listeners[$event])) {
            $this->listeners[$event] = array_filter(
                $this->listeners[$event],
                fn($handler) => $handler['listener'] !== $listener
            );
        }
    }

    /**
     * Check if event has listeners
     */
    public function hasListeners(string $event): bool
    {
        return !empty($this->getListeners($event));
    }

    /**
     * Get the dispatcher instance (singleton)
     */
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set the dispatcher instance
     */
    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Facade-style dispatch
     */
    public static function dispatch(Event $event): Event
    {
        return self::getInstance()->dispatch($event);
    }

    /**
     * Facade-style listen
     */
    public static function listen(string $event, callable|string $listener, int $priority = 0): void
    {
        self::getInstance()->listen($event, $listener, $priority);
    }
}