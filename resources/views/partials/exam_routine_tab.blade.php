<div class="space-y-5">

    <!-- Header & Distinct Exam Type Switcher (Midterm / Final) -->
    <div class="no-print bg-white border border-[#E1DFDD] rounded-[4px] p-5 shadow-[0_1.6px_3.6px_0_rgba(0,0,0,0.132)]">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-[#EFF6FC] text-[#0078D4] text-xs font-semibold mb-2 border border-[#C7E0F4]">
                    <svg class="w-3.5 h-3.5 text-[#0078D4]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    <span>DIU SWE Examination Schedule Portal</span>
                </div>
                <h2 class="text-xl font-bold text-[#323130]">Exam Routine &amp; Sheet Browser</h2>
                <p class="text-[#605E5C] text-xs sm:text-sm mt-1">
                    Select <strong>Midterm</strong> or <strong>Final</strong> examination, then browse and inspect the exam routine directly through your uploaded exam sheet (<code>.xlsx</code> or <code>.csv</code>).
                </p>
            </div>

            <!-- Distinct Exam Type Segmented Switcher -->
            <div class="flex items-center gap-2 bg-[#F3F2F1] p-1 rounded-[4px] border border-[#E1DFDD] shrink-0" role="navigation" aria-label="Exam type selection">
                @foreach(['Midterm', 'Final'] as $typeOption)
                    @php
                        $isSelectedType = ($examType === $typeOption);
                        $typeCount = $examCounts[$typeOption] ?? 0;
                    @endphp
                    <a href="{{ route('routine.index', ['tab' => 'exam_routine', 'exam_type' => $typeOption, 'exam_view_mode' => $examViewMode]) }}"
                       aria-current="{{ $isSelectedType ? 'page' : 'false' }}"
                       class="relative px-4 py-2 rounded-[4px] text-xs sm:text-sm font-bold transition flex items-center gap-2 {{ $isSelectedType ? 'bg-[#0078D4] text-white shadow-xs border-b-2 border-[#004578]' : 'text-[#323130] hover:bg-[#EDEBE9]' }}">
                        <span>{{ $typeOption }} Exam</span>
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold {{ $isSelectedType ? 'bg-white/20 text-white' : 'bg-white text-[#605E5C] border border-[#E1DFDD]' }}">
                            {{ $typeCount }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Validation Errors -->
        @if($errors->has('exam_sheet') || $errors->has('exam_type') || $errors->has('sheet_name'))
            <div class="mt-4 ms-messagebar ms-messagebar-error" role="alert">
                <svg class="w-4 h-4 text-[#A80000] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div class="text-xs font-semibold">
                    {{ $errors->first('exam_sheet') ?: ($errors->first('exam_type') ?: $errors->first('sheet_name')) }}
                </div>
            </div>
        @endif

        <!-- Browse Exam Sheet Uploader for Distinct Exam Type -->
        <div class="mt-5 pt-4 border-t border-[#E1DFDD]">
            <form method="POST" action="{{ route('exam-routine.upload') }}" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end">
                @csrf
                <input type="hidden" name="exam_type" value="{{ $examType }}">

                <div class="lg:col-span-6">
                    <label for="examSheetFile" class="block text-xs font-semibold text-[#323130] mb-1.5">
                        Browse {{ $examType }} Exam Sheet (<code>.xlsx</code> or <code>.csv</code>) <span class="text-[#A80000]" aria-hidden="true">*</span>
                    </label>
                    <input type="file"
                           id="examSheetFile"
                           name="exam_sheet"
                           accept=".xlsx,.csv,.txt"
                           required
                           aria-describedby="examSheetHelp"
                           class="block w-full text-xs text-[#323130] bg-[#FAF9F8] border border-[#E1DFDD] rounded-[4px] file:mr-3 file:py-2 file:px-3.5 file:rounded-l-[4px] file:border-0 file:text-xs file:font-bold file:bg-[#EFF6FC] file:text-[#0078D4] hover:file:bg-[#DEECF9] cursor-pointer focus:outline-none focus:border-[#0078D4]">
                    <p id="examSheetHelp" class="text-[11px] text-[#605E5C] mt-1">
                        Upload an official {{ $examType }} Excel workbook (multi-sheet supported) or CSV file to parse and browse slots.
                    </p>
                </div>

                <div class="lg:col-span-3">
                    <label for="preferredSheetName" class="block text-xs font-semibold text-[#323130] mb-1.5">
                        Worksheet Name (Optional)
                    </label>
                    <input type="text"
                           id="preferredSheetName"
                           name="sheet_name"
                           placeholder="Auto-detect {{ $examType }} sheet"
                           autocorrect="off"
                           spellcheck="false"
                           class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-3 py-2 text-xs text-[#323130] placeholder:text-[#A19F9D] focus:outline-none focus:border-[#0078D4] focus:ring-1 focus:ring-[#0078D4]">
                </div>

                <div class="lg:col-span-3 flex items-center gap-2">
                    <button type="submit" class="flex-1 bg-[#0078D4] hover:bg-[#106EBE] text-white font-semibold px-3.5 py-2 rounded-[4px] transition shadow-xs text-xs flex items-center justify-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        <span>Load {{ $examType }} Sheet</span>
                    </button>
                    <a href="{{ route('exam-routine.template', ['exam_type' => $examType]) }}"
                       title="Download Sample {{ $examType }} CSV Template"
                       class="px-3 py-2 rounded-[4px] bg-white hover:bg-[#EDEBE9] border border-[#E1DFDD] text-xs font-semibold text-[#323130] transition shrink-0">
                        Template
                    </a>
                </div>
            </form>

            <!-- Active Sheet Metadata & Multi-Sheet Switcher -->
            @if($activeExamSheet)
                <div class="mt-4 p-3.5 rounded-[4px] bg-[#FAF9F8] border border-[#E1DFDD] flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="flex items-start gap-2.5 text-xs">
                        <div class="w-8 h-8 rounded-[4px] bg-[#DFF6DD] text-[#107C41] border border-[#9FD89F] flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <div>
                            <div class="font-bold text-[#323130] flex items-center gap-2 flex-wrap">
                                <span>Active {{ $examType }} Sheet: {{ $activeExamSheet->original_filename }}</span>
                                <span class="px-1.5 py-0.5 rounded bg-[#EFF6FC] text-[#0078D4] border border-[#C7E0F4] text-[10px] font-mono">
                                    Tab: {{ $activeExamSheet->active_sheet_name }}
                                </span>
                            </div>
                            <div class="text-[11px] text-[#605E5C] mt-0.5">
                                Parsed <strong>{{ $activeExamSheet->row_count }}</strong> structured exam entries
                                @if(!empty($activeExamSheet->raw_rows))
                                    • <strong>{{ count($activeExamSheet->raw_rows) }}</strong> raw sheet rows
                                @endif
                                @if($activeExamSheet->uploaded_at)
                                    • Loaded {{ $activeExamSheet->uploaded_at->diffForHumans() }}
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if(is_array($activeExamSheet->available_sheets) && count($activeExamSheet->available_sheets) > 1)
                            <form method="POST" action="{{ route('exam-routine.select-sheet') }}" class="flex items-center gap-1.5">
                                @csrf
                                <input type="hidden" name="exam_type" value="{{ $examType }}">
                                <label for="activeWorksheetSelect" class="sr-only">Select Worksheet</label>
                                <select id="activeWorksheetSelect" name="sheet_name" class="bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs font-semibold text-[#323130] focus:outline-none focus:border-[#0078D4]">
                                    @foreach($activeExamSheet->available_sheets as $wsName)
                                        <option value="{{ $wsName }}" {{ $activeExamSheet->active_sheet_name === $wsName ? 'selected' : '' }}>
                                            Sheet: {{ $wsName }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="px-2.5 py-1.5 rounded-[4px] bg-[#EFF6FC] hover:bg-[#DEECF9] text-[#0078D4] border border-[#C7E0F4] text-xs font-bold transition cursor-pointer">
                                    Switch Tab
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('exam-routine.clear') }}" onsubmit="return confirm('Clear the {{ $examType }} exam sheet and all {{ $examType }} slots?');">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="exam_type" value="{{ $examType }}">
                            <button type="submit" class="px-2.5 py-1.5 rounded-[4px] bg-white hover:bg-[#FDE7E9] text-[#A80000] border border-[#E1DFDD] text-xs font-semibold transition cursor-pointer">
                                Clear {{ $examType }} Sheet
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Optional Quick Manual Entry Disclosure -->
            <details class="mt-3 rounded-[4px] border border-[#E1DFDD] bg-[#FAF9F8]">
                <summary class="px-3.5 py-2 text-xs font-semibold text-[#0078D4] cursor-pointer hover:bg-[#EFF6FC] transition select-none">
                    + Add an individual {{ $examType }} exam slot manually
                </summary>
                <form method="POST" action="{{ route('exam-routine.slot.store') }}" class="p-3.5 border-t border-[#E1DFDD] bg-white grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2.5 items-end">
                    @csrf
                    <input type="hidden" name="exam_type" value="{{ $examType }}">

                    <div>
                        <label for="manualExamDate" class="block text-[11px] font-semibold text-[#323130] mb-1">Exam Date</label>
                        <input id="manualExamDate" type="date" name="exam_date" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130]">
                    </div>
                    <div>
                        <label for="manualTimeSlot" class="block text-[11px] font-semibold text-[#323130] mb-1">Time Slot *</label>
                        <input id="manualTimeSlot" type="text" name="time_slot" required value="{{ \App\Models\ExamRoutine::DEFAULT_SLOTS[$examType][0] ?? '09:00 AM - 11:00 AM' }}" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130]">
                    </div>
                    <div>
                        <label for="manualCourseCode" class="block text-[11px] font-semibold text-[#323130] mb-1">Course Code *</label>
                        <input id="manualCourseCode" type="text" name="course_code" required placeholder="e.g. SE214" autocorrect="off" autocapitalize="characters" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs uppercase text-[#323130]">
                    </div>
                    <div>
                        <label for="manualBatch" class="block text-[11px] font-semibold text-[#323130] mb-1">Batch</label>
                        <select id="manualBatch" name="batch" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130]">
                            <option value="">All / General</option>
                            @foreach($availableBatches as $b)
                                <option value="{{ $b }}">Batch {{ $b }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="manualRoom" class="block text-[11px] font-semibold text-[#323130] mb-1">Room No</label>
                        <input id="manualRoom" type="text" name="room_no" placeholder="e.g. 610" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130]">
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-[#0078D4] hover:bg-[#106EBE] text-white font-semibold px-3 py-1.5 rounded-[4px] text-xs transition cursor-pointer">
                            Add Slot
                        </button>
                    </div>
                </form>
            </details>
        </div>
    </div>

    <!-- Filter & View Mode Control Bar -->
    <div class="no-print bg-white border border-[#E1DFDD] rounded-[4px] p-4 shadow-[0_1.6px_3.6px_0_rgba(0,0,0,0.08)] flex flex-col lg:flex-row lg:items-end justify-between gap-3">
        <search class="flex-1">
            <form method="GET" action="{{ route('routine.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-end">
                <input type="hidden" name="tab" value="exam_routine">
                <input type="hidden" name="exam_type" value="{{ $examType }}">
                <input type="hidden" name="exam_view_mode" value="{{ $examViewMode }}">

                <div class="sm:col-span-3">
                    <label for="examBatchFilter" class="block text-xs font-semibold text-[#323130] mb-1">Batch</label>
                    <select id="examBatchFilter" name="exam_batch" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs sm:text-sm text-[#323130] focus:outline-none focus:border-[#0078D4]">
                        <option value="">All Batches</option>
                        @foreach($availableBatches as $b)
                            <option value="{{ $b }}" {{ $examBatch === $b ? 'selected' : '' }}>Batch {{ $b }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-6">
                    <label for="examSearchInput" class="block text-xs font-semibold text-[#323130] mb-1">Search Exam Routine</label>
                    <input type="search"
                           id="examSearchInput"
                           name="exam_search"
                           value="{{ $examSearch }}"
                           placeholder="Course code, title, room, section, or date..."
                           autocorrect="off"
                           spellcheck="false"
                           class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-3 py-1.5 text-xs sm:text-sm text-[#323130] placeholder:text-[#A19F9D] focus:outline-none focus:border-[#0078D4]">
                </div>

                <div class="sm:col-span-3 flex items-center gap-1.5">
                    <button type="submit" class="flex-1 bg-[#0078D4] hover:bg-[#106EBE] text-white font-semibold px-3 py-1.5 rounded-[4px] text-xs sm:text-sm transition cursor-pointer">
                        Filter
                    </button>
                    @if($examBatch || $examSearch)
                        <a href="{{ route('routine.index', ['tab' => 'exam_routine', 'exam_type' => $examType, 'exam_view_mode' => $examViewMode]) }}" class="px-2.5 py-1.5 rounded-[4px] bg-[#FAF9F8] hover:bg-[#EDEBE9] border border-[#E1DFDD] text-xs font-semibold text-[#605E5C]">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </search>

        <!-- View Mode Switcher & Exports -->
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <div class="inline-flex rounded-[4px] bg-[#F3F2F1] p-0.5 border border-[#E1DFDD] text-xs font-semibold">
                <a href="{{ route('routine.index', ['tab' => 'exam_routine', 'exam_type' => $examType, 'exam_batch' => $examBatch, 'exam_search' => $examSearch, 'exam_view_mode' => 'schedule']) }}"
                   class="px-2.5 py-1.5 rounded-[4px] transition {{ $examViewMode === 'schedule' ? 'bg-[#0078D4] text-white shadow-xs' : 'text-[#605E5C] hover:text-[#323130]' }}">
                    Schedule Table
                </a>
                <a href="{{ route('routine.index', ['tab' => 'exam_routine', 'exam_type' => $examType, 'exam_batch' => $examBatch, 'exam_search' => $examSearch, 'exam_view_mode' => 'sheet']) }}"
                   class="px-2.5 py-1.5 rounded-[4px] transition {{ $examViewMode === 'sheet' ? 'bg-[#0078D4] text-white shadow-xs' : 'text-[#605E5C] hover:text-[#323130]' }}">
                    Raw Exam Sheet
                </a>
            </div>

            @if($examRoutines->isNotEmpty())
                <a href="{{ route('exam-routine.export.ics', ['exam_type' => $examType, 'exam_batch' => $examBatch, 'exam_search' => $examSearch]) }}"
                   class="px-2.5 py-1.5 rounded-[4px] bg-[#0078D4] hover:bg-[#106EBE] text-white text-xs font-semibold transition">
                    Outlook (.ics)
                </a>
                <a href="{{ route('exam-routine.export.csv', ['exam_type' => $examType, 'exam_batch' => $examBatch, 'exam_search' => $examSearch]) }}"
                   class="px-2.5 py-1.5 rounded-[4px] bg-[#DFF6DD] hover:bg-[#C7E0C7] text-[#107C41] border border-[#9FD89F] text-xs font-semibold transition">
                    Export CSV
                </a>
            @endif
        </div>
    </div>

    <!-- Exam Routine Results Display -->
    @if($examViewMode === 'sheet')
        <div class="bg-white border border-[#E1DFDD] rounded-[4px] p-5 shadow-[0_1.6px_3.6px_0_rgba(0,0,0,0.132)] space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-[#E1DFDD]">
                <div>
                    <h3 id="raw-exam-sheet-caption" class="text-base font-bold text-[#323130]">
                        {{ $examType }} Raw Exam Sheet Browser
                    </h3>
                    <p class="text-xs text-[#605E5C]">
                        @if($activeExamSheet)
                            Showing spreadsheet contents of <strong>{{ $activeExamSheet->original_filename }}</strong> (Tab: <strong>{{ $activeExamSheet->active_sheet_name }}</strong>)
                        @else
                            No exam sheet uploaded yet for {{ $examType }} examination.
                        @endif
                    </p>
                </div>
                @if($activeExamSheet && !empty($activeExamSheet->raw_rows))
                    <div>
                        <label for="rawSheetFilterInput" class="sr-only">Filter sheet rows</label>
                        <input type="search" id="rawSheetFilterInput" onkeyup="filterRawExamSheetRows()" placeholder="Filter sheet rows..." class="bg-white border border-[#E1DFDD] rounded-[4px] px-3 py-1.5 text-xs text-[#323130] w-full sm:w-64 focus:outline-none focus:border-[#0078D4]">
                    </div>
                @endif
            </div>

            @if($activeExamSheet && !empty($activeExamSheet->headers) && !empty($activeExamSheet->raw_rows))
                <div tabindex="0" role="region" aria-labelledby="raw-exam-sheet-caption" class="overflow-x-auto max-h-[600px] border border-[#E1DFDD] rounded-[4px]">
                    <table id="rawExamSheetTable" class="w-full text-left text-xs divide-y divide-[#E1DFDD]">
                        <thead class="sticky top-0 z-10 bg-[#0078D4] text-white">
                            <tr>
                                <th class="py-2.5 px-3 font-bold uppercase text-[11px] border-r border-[#106EBE] w-12 text-center">#</th>
                                @foreach($activeExamSheet->headers as $header)
                                    <th class="py-2.5 px-3 font-bold uppercase text-[11px] border-r border-[#106EBE] last:border-r-0 whitespace-nowrap">
                                        {{ $header }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E1DFDD] bg-white">
                            @foreach($activeExamSheet->raw_rows as $rIdx => $row)
                                <tr class="hover:bg-[#FAF9F8]">
                                    <td class="py-2 px-3 font-mono text-[11px] text-[#605E5C] bg-[#FAF9F8] border-r border-[#E1DFDD] text-center">{{ $rIdx + 1 }}</td>
                                    @foreach($activeExamSheet->headers as $cIdx => $header)
                                        <td class="py-2 px-3 text-[#323130] border-r border-[#E1DFDD] last:border-r-0 whitespace-nowrap">
                                            {{ $row[$cIdx] ?? '' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-8 text-center border border-dashed border-[#D2D0CE] rounded-[4px] bg-[#FAF9F8]">
                    <p class="font-bold text-sm text-[#323130]">No {{ $examType }} Exam Sheet Uploaded Yet</p>
                    <p class="text-xs text-[#605E5C] mt-1">Use the "Browse {{ $examType }} Exam Sheet" uploader above to load an Excel (<code>.xlsx</code>) or CSV sheet.</p>
                </div>
            @endif
        </div>
    @else
        <div id="examRoutineContainer" class="rounded-[4px] border border-[#E1DFDD] bg-white shadow-[0_1.6px_3.6px_0_rgba(0,0,0,0.132)] overflow-hidden">
            <div class="px-4 py-3 bg-[#0078D4] text-white flex items-center justify-between border-b border-[#106EBE]">
                <div>
                    <div class="text-sm font-bold tracking-wide">
                        Daffodil International University • Dept of SWE
                    </div>
                    <div id="exam-schedule-caption" class="text-[11px] text-[#EFF6FC] font-medium">
                        {{ $examType }} Examination Routine • Fall 2026
                        @if($examBatch) • Batch {{ $examBatch }} @endif
                        @if($activeExamSheet) • Sheet: {{ $activeExamSheet->active_sheet_name }} @endif
                    </div>
                </div>
                <span class="px-2.5 py-1 rounded bg-white/20 text-white text-xs font-mono font-bold">
                    {{ $examRoutines->count() }} Exams
                </span>
            </div>

            @if($examRoutines->isNotEmpty())
                <div tabindex="0" role="region" aria-labelledby="exam-schedule-caption" class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm divide-y divide-[#E1DFDD]">
                        <thead>
                            <tr class="bg-[#F3F2F1] text-[#323130] font-bold uppercase text-[11px] tracking-wider">
                                <th class="py-2.5 px-3">Date &amp; Day</th>
                                <th class="py-2.5 px-3">Time Slot</th>
                                <th class="py-2.5 px-3">Course</th>
                                <th class="py-2.5 px-3">Batch / Sec</th>
                                <th class="py-2.5 px-3">Room &amp; Building</th>
                                <th class="py-2.5 px-3">Faculty</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E1DFDD] bg-white">
                            @foreach($examRoutines as $exam)
                                <tr class="hover:bg-[#FAF9F8] transition">
                                    <td class="py-2.5 px-3 whitespace-nowrap">
                                        <div class="font-bold text-[#323130] font-mono text-xs">{{ $exam->exam_date ?? 'TBA' }}</div>
                                        @if($exam->day_of_week)
                                            <div class="text-[11px] text-[#605E5C]">{{ $exam->day_of_week }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 whitespace-nowrap font-mono font-semibold text-xs text-[#0078D4]">
                                        {{ $exam->time_slot }}
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-[#323130]">{{ $exam->course_code }}</div>
                                        @if($exam->course_name && $exam->course_name !== $exam->course_code)
                                            <div class="text-xs text-[#605E5C]">{{ $exam->course_name }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 whitespace-nowrap">
                                        @if($exam->batch)
                                            <span class="px-2 py-0.5 rounded bg-[#EFF6FC] text-[#0078D4] border border-[#C7E0F4] text-xs font-bold">
                                                Batch {{ $exam->batch }}@if($exam->section)-{{ $exam->section }}@endif
                                            </span>
                                        @elseif($exam->section)
                                            <span class="px-2 py-0.5 rounded bg-[#F3F2F1] text-[#323130] text-xs font-semibold">Sec {{ $exam->section }}</span>
                                        @else
                                            <span class="text-[#A19F9D] text-xs">All</span>
                                        @endif
                                        @if($exam->major_track)
                                            <span class="ml-1 px-1.5 py-0.5 rounded bg-[#FFF4CE] text-[#8A3707] text-[10px] font-bold">{{ $exam->major_track }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 whitespace-nowrap">
                                        @if($exam->room_no)
                                            <span class="font-bold text-[#107C41]">Room {{ $exam->room_no }}</span>
                                            @if($exam->building)
                                                <span class="text-xs text-[#605E5C] font-mono">({{ $exam->building }})</span>
                                            @endif
                                        @else
                                            <span class="text-[#A19F9D] text-xs">TBA</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 whitespace-nowrap">
                                        @if($exam->teacher_initials)
                                            @php $examFac = \App\Services\FacultyService::getFaculty($exam->teacher_initials); @endphp
                                            <span class="px-1.5 py-0.5 rounded bg-[#EFF6FC] text-[#0078D4] font-mono text-[11px] font-bold">{{ $exam->teacher_initials }}</span>
                                            <span class="text-xs text-[#323130] ml-1">{{ $examFac['name'] }}</span>
                                        @else
                                            <span class="text-[#A19F9D] text-xs">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-10 text-center space-y-2">
                    <div class="w-12 h-12 mx-auto rounded-full bg-[#EFF6FC] text-[#0078D4] flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <h3 class="text-base font-bold text-[#323130]">No {{ $examType }} Exam Routine Loaded</h3>
                    <p class="text-xs text-[#605E5C] max-w-md mx-auto">
                        @if($examBatch || $examSearch)
                            No {{ $examType }} exam slots matched your current filter. Try clearing the search or batch filter.
                        @else
                            Browse and upload your {{ $examType }} exam sheet (<code>.xlsx</code> or <code>.csv</code>) above, or download the CSV template to get started.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    @endif
</div>

