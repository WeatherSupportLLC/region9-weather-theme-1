<?php
defined('ABSPATH') || exit;

class R9LS_Timing_Engine {
    const TZ = 'America/Chicago';

    public function normalize($value, $reference = 'now') {
        if (is_array($value)) {
            return $this->range($value['start'] ?? '', $value['end'] ?? '', $reference);
        }
        $value = trim((string) $value);
        if ($value === '') { return array('raw' => '', 'label' => 'Timing unavailable.', 'local' => ''); }
        if (preg_match('/^(.+)[–-](.+)$/u', $value, $m)) { return $this->range(trim($m[1]), trim($m[2]), $reference); }
        $dt = $this->to_central($value, $reference);
        if (!$dt) { return array('raw' => $value, 'label' => $value, 'local' => $value); }
        return array('raw' => $value, 'label' => $this->daypart((int) $dt->format('G')), 'local' => $this->format_time($dt), 'iso' => $dt->format(DateTime::ATOM));
    }

    public function range($start, $end, $reference = 'now') {
        $s = $this->to_central($start, $reference); $e = $this->to_central($end, $reference);
        if (!$s || !$e) { return array('raw' => trim($start . '–' . $end, '–'), 'label' => 'Timing unavailable.', 'local' => trim($start . '–' . $end, '–')); }
        if ($e <= $s) { $e->modify('+1 day'); }
        $label = $this->daypart((int) $s->format('G'));
        if ($this->daypart((int) $e->format('G')) !== $label) { $label .= ' into ' . $this->daypart((int) $e->format('G')); }
        return array('raw' => $start . '–' . $end, 'label' => $label, 'local' => $this->format_range($s, $e), 'start_iso' => $s->format(DateTime::ATOM), 'end_iso' => $e->format(DateTime::ATOM));
    }

    public function to_central($value, $reference = 'now') {
        try {
            $value = trim((string) $value);
            if (preg_match('/^(\d{1,2})Z$/i', $value, $m)) {
                $base = new DateTime($reference === 'now' ? 'now' : $reference, new DateTimeZone('UTC'));
                $base->setTimezone(new DateTimeZone('UTC'))->setTime((int) $m[1], 0, 0);
            } else {
                $base = new DateTime($value, new DateTimeZone('UTC'));
            }
            return $base->setTimezone(new DateTimeZone(self::TZ));
        } catch (Exception $e) { return null; }
    }

    public function daypart($hour) {
        if ($hour >= 5 && $hour < 10) { return 'Morning'; }
        if ($hour >= 10 && $hour < 12) { return 'Late Morning'; }
        if ($hour >= 12 && $hour < 16) { return 'Afternoon'; }
        if ($hour >= 16 && $hour < 18) { return 'Late Afternoon'; }
        if ($hour >= 18 && $hour < 23) { return 'Evening'; }
        return 'Overnight';
    }

    private function format_time($dt) { return ltrim($dt->format('g A T'), '0'); }
    private function format_range($s, $e) { return ltrim($s->format('g A'), '0') . '–' . ltrim($e->format('g A T'), '0'); }
}
