
import { ref } from "vue";

export type PosSoundType =
    | "success"
    | "duplicate"
    | "error"
    | "warning"
    | "transaction";

interface SoundTone {
    frequency: number;
    duration: number;
    type?: OscillatorType;
}

const STORAGE_KEY = "acn-pos-sound-enabled";

function getInitialSoundPreference(): boolean {
    try {
        return localStorage.getItem(STORAGE_KEY) !== "false";
    } catch {
        return true;
    }
}

const soundEnabled = ref(getInitialSoundPreference());

let audioContext: AudioContext | null = null;

const soundPatterns: Record<PosSoundType, SoundTone[]> = {
    success: [
        { frequency: 1050, duration: 90 },
    ],
    duplicate: [
        { frequency: 850, duration: 65 },
        { frequency: 1050, duration: 65 },
    ],
    error: [
        { frequency: 300, duration: 220, type: "square" },
    ],
    warning: [
        { frequency: 480, duration: 140, type: "triangle" },
        { frequency: 380, duration: 140, type: "triangle" },
    ],
    transaction: [
        { frequency: 900, duration: 100 },
        { frequency: 1200, duration: 120 },
        { frequency: 1500, duration: 180 },
    ],
};

function getAudioContext(): AudioContext | null {
    if (typeof window === "undefined") return null;

    const AudioContextClass = window.AudioContext;

    if (!AudioContextClass) return null;

    audioContext ??= new AudioContextClass();

    return audioContext;
}

async function unlock(): Promise<void> {
    try {
        const context = getAudioContext();

        if (context?.state === "suspended") {
            await context.resume();
        }
    } catch {
        // Audio tidak boleh mengganggu proses POS.
    }
}

async function play(sound: PosSoundType): Promise<void> {
    if (!soundEnabled.value) return;

    try {
        const context = getAudioContext();

        if (!context) return;

        if (context.state === "suspended") {
            await context.resume();
        }

        const tones = soundPatterns[sound];
        let startAt = context.currentTime;

        for (const tone of tones) {
            const oscillator = context.createOscillator();
            const gain = context.createGain();

            const duration = tone.duration / 1000;

            oscillator.type = tone.type ?? "sine";
            oscillator.frequency.setValueAtTime(
                tone.frequency,
                startAt,
            );

            gain.gain.setValueAtTime(0.0001, startAt);
            gain.gain.linearRampToValueAtTime(
                0.12,
                startAt + 0.01,
            );
            gain.gain.exponentialRampToValueAtTime(
                0.0001,
                startAt + duration,
            );

            oscillator.connect(gain);
            gain.connect(context.destination);

            oscillator.start(startAt);
            oscillator.stop(startAt + duration);

            startAt += duration + 0.045;
        }
    } catch {
        // Kegagalan audio tidak boleh menggagalkan transaksi.
    }
}

function setEnabled(enabled: boolean): void {
    soundEnabled.value = enabled;

    try {
        localStorage.setItem(STORAGE_KEY, String(enabled));
    } catch {
        // Tetap gunakan preferensi selama sesi berjalan.
    }

    if (enabled) {
        void unlock();
    }
}

function toggle(): void {
    setEnabled(!soundEnabled.value);
}

export function usePosSound() {
    return {
        soundEnabled,
        play,
        unlock,
        setEnabled,
        toggle,
    };
}

/* import { ref } from "vue";

type PosSoundType =
    | "success"
    | "duplicate"
    | "error"
    | "warning"
    | "transaction";

const soundEnabled = ref(true);

const frequencies: Record<
    PosSoundType,
    { frequency: number; duration: number; type: OscillatorType }
> = {
    success: { frequency: 1100, duration: 90, type: "sine" },
    duplicate: { frequency: 850, duration: 70, type: "sine" },
    error: { frequency: 280, duration: 250, type: "square" },
    warning: { frequency: 450, duration: 180, type: "triangle" },
    transaction: { frequency: 1300, duration: 160, type: "sine" },
};

export function usePosSound() {
    function play(type: PosSoundType): void {
        if (!soundEnabled.value) return;

        try {
            const AudioContextClass =
                window.AudioContext ||
                (window as typeof window & {
                    webkitAudioContext?: typeof AudioContext;
                }).webkitAudioContext;

            if (!AudioContextClass) return;

            const context = new AudioContextClass();
            const oscillator = context.createOscillator();
            const gain = context.createGain();

            const sound = frequencies[type];

            oscillator.type = sound.type;
            oscillator.frequency.value = sound.frequency;

            gain.gain.setValueAtTime(0.15, context.currentTime);
            gain.gain.exponentialRampToValueAtTime(
                0.001,
                context.currentTime + sound.duration / 1000,
            );

            oscillator.connect(gain);
            gain.connect(context.destination);

            oscillator.start();
            oscillator.stop(
                context.currentTime + sound.duration / 1000,
            );

            oscillator.onended = () => {
                void context.close();
            };
        } catch {
            // Audio tidak boleh mengganggu proses transaksi.
        }
    }

    function toggle(): void {
        soundEnabled.value = !soundEnabled.value;
    }

    return {
        soundEnabled,
        play,
        toggle,
    };
}
 */
