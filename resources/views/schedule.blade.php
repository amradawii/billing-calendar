<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Billing calendar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-[15px] leading-relaxed text-gray-800 antialiased">
<main class="mx-auto max-w-6xl px-6 py-8" x-data="schedule(@js($scenarios))">
    <h1 class="text-xl font-semibold text-gray-900">Billing calendar</h1>
    <p class="mt-1 text-gray-600">Renewal dates and reminders that survive month-ends, leap years and daylight saving time.</p>

    <div class="mt-6 flex flex-wrap border-b border-gray-200" role="tablist">
        @foreach ($scenarios as $scenario)
            <button type="button" role="tab" data-tab="{{ $scenario['id'] }}"
                    class="-mb-px border-b-2 px-3 py-2 text-sm"
                    :class="active === '{{ $scenario['id'] }}' ? 'border-teal-700 font-medium text-teal-800' : 'border-transparent text-gray-600 hover:text-gray-900'"
                    :aria-selected="active === '{{ $scenario['id'] }}'"
                    @click="select('{{ $scenario['id'] }}')">{{ $scenario['label'] }}</button>
        @endforeach
    </div>

    <section class="mt-6 grid gap-6 md:grid-cols-2" x-show="scenario.problem" x-cloak>
        <div>
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">The problem</h2>
            <p class="mt-1" x-text="scenario.problem"></p>
        </div>
        <div>
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">How the code solves it</h2>
            <p class="mt-1" x-text="scenario.solution"></p>
        </div>
    </section>

    <p class="mt-6 text-sm text-gray-600" x-show="active !== 'custom'" x-cloak>
        <span x-text="settings"></span>.
        <button type="button" class="ml-1 text-teal-700 underline hover:text-teal-900" @click="customize()">Edit these settings</button>
    </p>

    <form class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4" x-show="active === 'custom'" x-cloak @submit.prevent="run()">
        <label class="block">
            <span class="text-xs font-medium text-gray-600">Start date</span>
            <input type="date" x-model="form.start" required class="field">
            <span class="error" x-text="errors.start?.[0]"></span>
        </label>
        <label class="block">
            <span class="text-xs font-medium text-gray-600">Frequency</span>
            <select x-model="form.frequency" class="field">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
            </select>
            <span class="error" x-text="errors.frequency?.[0]"></span>
        </label>
        <label class="block">
            <span class="text-xs font-medium text-gray-600">Every (interval)</span>
            <input type="number" min="1" max="100" x-model="form.interval" required class="field">
            <span class="error" x-text="errors.interval?.[0]"></span>
        </label>
        <label class="block">
            <span class="text-xs font-medium text-gray-600">Local time</span>
            <input type="time" x-model="form.time" required class="field">
            <span class="error" x-text="errors.time?.[0]"></span>
        </label>
        <label class="block">
            <span class="text-xs font-medium text-gray-600">Time zone (type to search)</span>
            <input type="text" list="zones" x-model="form.zone" required autocomplete="off" spellcheck="false" class="field">
            <datalist id="zones">
                @foreach ($zones as $zone)
                    <option value="{{ $zone }}"></option>
                @endforeach
            </datalist>
            <span class="error" x-text="errors.zone?.[0]"></span>
        </label>
        <label class="block">
            <span class="text-xs font-medium text-gray-600">Occurrences (1-60)</span>
            <input type="number" min="1" max="60" x-model="form.count" required class="field">
            <span class="error" x-text="errors.count?.[0]"></span>
        </label>
        <label class="col-span-2 block md:col-span-1">
            <span class="text-xs font-medium text-gray-600">If the time does not exist (DST gap)</span>
            <select x-model="form.gap" class="field">
                <option value="shift_forward">Shift forward (02:30 to 03:30)</option>
                <option value="next_valid_minute">First valid minute (03:00)</option>
            </select>
            <span class="error" x-text="errors.gap?.[0]"></span>
        </label>
        <label class="col-span-2 block md:col-span-1">
            <span class="text-xs font-medium text-gray-600">If the time happens twice (DST overlap)</span>
            <select x-model="form.overlap" class="field">
                <option value="earlier">The first one (summer time)</option>
                <option value="later">The second one (winter time)</option>
            </select>
            <span class="error" x-text="errors.overlap?.[0]"></span>
        </label>
        <div class="col-span-2 md:col-span-4">
            <button type="submit" class="rounded bg-teal-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-teal-800">Compare</button>
        </div>
    </form>

    <section class="mt-6">
        <p class="text-sm text-gray-500" x-show="loading && !result" x-cloak>Computing...</p>
        <p class="text-sm text-red-700" x-show="failed" x-cloak>The schedule could not be loaded. Is the server running?</p>
        <p class="text-sm text-red-700" x-show="Object.keys(errors).length && active !== 'custom'" x-cloak>These settings are not valid. Open "Edit these settings" to fix them.</p>

        <template x-if="result">
            <div>
                <p class="text-sm" data-summary>
                    <span class="font-semibold" x-text="result.summary.differing"></span>
                    of <span x-text="result.summary.total"></span> occurrences differ.
                    <span class="text-gray-500">Rows that differ are highlighted.</span>
                </p>

                <div class="mt-3 overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                        <tr class="border-b border-gray-300 text-left text-xs uppercase tracking-wide text-gray-500">
                            <th class="w-10 py-2 pr-3 font-medium">#</th>
                            <th class="w-56 py-2 pr-4 font-medium">Naive</th>
                            <th class="w-56 py-2 pr-4 font-medium">Correct</th>
                            <th class="py-2 font-medium">Explanation</th>
                        </tr>
                        </thead>
                        <tbody>
                        <template x-for="row in rows" :key="row.index">
                            <tr class="border-b border-gray-100 align-top" :class="row.reason ? 'bg-amber-50' : ''" :data-differs="row.reason ? 'true' : 'false'">
                                <td class="py-2 pl-1 pr-3 tabular-nums text-gray-500" x-text="row.index + 1"></td>
                                <td class="py-2 pr-4 tabular-nums">
                                    <div :class="row.reason ? 'text-red-700' : ''" x-text="when(row.naive)"></div>
                                    <div class="text-xs text-gray-500" x-text="instant(row.naive)"></div>
                                </td>
                                <td class="py-2 pr-4 tabular-nums">
                                    <div x-text="when(row.correct)"></div>
                                    <div class="text-xs text-gray-500" x-text="instant(row.correct)"></div>
                                </td>
                                <td class="py-2 pr-1 text-gray-700">
                                    <template x-for="note in row.correct.notes">
                                        <p x-text="note"></p>
                                    </template>
                                    <p x-show="row.reason" :class="row.correct.notes.length ? 'mt-1' : ''" x-text="row.reason"></p>
                                </td>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </section>
</main>
</body>
</html>
