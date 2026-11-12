<div class="space-y-5">

    <!-- Header Strip -->
    <div class="no-print bg-white border border-[#E1DFDD] rounded-[4px] p-5 shadow-[0_1.6px_3.6px_0_rgba(0,0,0,0.132)]">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] bg-[#EFF6FC] text-[#0078D4] text-xs font-semibold mb-2 border border-[#C7E0F4]">
                    <svg class="w-3.5 h-3.5 text-[#0078D4]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>DIU SWE Academic Document Studio</span>
                </div>
                <h2 class="text-xl font-bold text-[#323130]">Assignment &amp; Report Cover Page Generator</h2>
                <p class="text-[#605E5C] text-xs sm:text-sm mt-1">
                    Create official A4 Portrait cover pages for <strong>Lab Reports</strong>, <strong>Final Lab Reports</strong>, <strong>Theory Assessment Reports</strong>, and <strong>Assignments</strong> with auto-filled DIU SWE courses &amp; faculty.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <button type="button" onclick="printCoverPage()" class="px-3.5 py-2 rounded-[4px] bg-[#FAF9F8] hover:bg-[#EDEBE9] text-[#323130] border border-[#E1DFDD] text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-2xs">
                    <svg class="w-4 h-4 text-[#0078D4]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Print / Save PDF (A4)</span>
                </button>
                <button type="button" onclick="exportCoverPageImage()" class="px-3.5 py-2 rounded-[4px] bg-[#0078D4] hover:bg-[#106EBE] text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    <span>Download PNG (A4)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Two-Column Cover Page Studio -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">

        <!-- LEFT COLUMN: CONFIGURATION FORM -->
        <div class="no-print xl:col-span-5 bg-white border border-[#E1DFDD] rounded-[4px] p-5 shadow-[0_1.6px_3.6px_0_rgba(0,0,0,0.132)] space-y-5">
            <form id="coverPageForm" onsubmit="return false;" class="space-y-4">

                <!-- 1. Cover Page Type Selector (4 Distinct Templates) -->
                <fieldset class="space-y-2">
                    <legend class="text-xs font-bold uppercase tracking-wider text-[#323130] mb-1">
                        1. Select Cover Page Type
                    </legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @php
                            $coverTemplates = [
                                'lab_report' => ['title' => 'Lab Report', 'desc' => 'Weekly / Experiment Lab'],
                                'final_lab_report' => ['title' => 'Final Lab Report', 'desc' => 'Complete Lab Portfolio'],
                                'theory_assessment' => ['title' => 'Theory Assessment Report', 'desc' => 'Case Study / Term Paper'],
                                'assignment' => ['title' => 'Assignment', 'desc' => 'Course Assignment'],
                            ];
                        @endphp
                        @foreach($coverTemplates as $tplKey => $tplInfo)
                            <label for="coverType_{{ $tplKey }}" class="flex items-start gap-2.5 p-2.5 rounded-[4px] border border-[#E1DFDD] bg-[#FAF9F8] hover:bg-[#EFF6FC] has-[:checked]:bg-[#EFF6FC] has-[:checked]:border-[#0078D4] cursor-pointer transition">
                                <input type="radio"
                                       id="coverType_{{ $tplKey }}"
                                       name="cover_type"
                                       value="{{ $tplKey }}"
                                       {{ $coverType === $tplKey ? 'checked' : '' }}
                                       onchange="updateCoverPreview()"
                                       class="mt-0.5 accent-[#0078D4]">
                                <span class="text-xs leading-tight">
                                    <strong class="block text-[#323130]">{{ $tplInfo['title'] }}</strong>
                                    <span class="text-[11px] text-[#605E5C]">{{ $tplInfo['desc'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <!-- 2. Course & Document Topic -->
                <div class="pt-3 border-t border-[#E1DFDD] space-y-3">
                    <div class="text-xs font-bold uppercase tracking-wider text-[#323130]">
                        2. Course &amp; Document Details
                    </div>

                    <div>
                        <label for="coverCoursePreset" class="block text-xs font-semibold text-[#323130] mb-1">
                            Quick-Fill DIU SWE Course
                        </label>
                        <select id="coverCoursePreset" onchange="applyCoverCoursePreset(this)" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-3 py-1.5 text-xs text-[#323130] focus:outline-none focus:border-[#0078D4]">
                            <option value="">-- Choose a Fall 2026 Course (or type manually below) --</option>
                            @foreach($coverCourses as $course)
                                <option value="{{ $course->course_code }}" data-title="{{ $course->course_name }}">
                                    {{ $course->course_code }} — {{ $course->course_name }} @if($course->batch > 0)(Batch {{ $course->batch }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <div>
                            <label for="coverCourseCode" class="block text-xs font-semibold text-[#323130] mb-1">Course Code</label>
                            <input id="coverCourseCode" type="text" value="SE215" autocorrect="off" autocapitalize="characters" oninput="updateCoverPreview()" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs font-mono font-bold uppercase text-[#323130] focus:outline-none focus:border-[#0078D4]">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="coverCourseTitle" class="block text-xs font-semibold text-[#323130] mb-1">Course Title</label>
                            <input id="coverCourseTitle" type="text" value="Algorithms Design &amp; Analysis Lab" oninput="updateCoverPreview()" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130] focus:outline-none focus:border-[#0078D4]">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <div>
                            <label id="coverDocNumberLabel" for="coverDocNumber" class="block text-xs font-semibold text-[#323130] mb-1">Experiment No.</label>
                            <input id="coverDocNumber" type="text" value="Experiment No. 01" oninput="updateCoverPreview()" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130] focus:outline-none focus:border-[#0078D4]">
                        </div>
                        <div class="sm:col-span-2">
                            <label id="coverDocTitleLabel" for="coverDocTitle" class="block text-xs font-semibold text-[#323130] mb-1">Experiment / Report Title</label>
                            <input id="coverDocTitle" type="text" value="Asymptotic Complexity &amp; Divide-and-Conquer Sorting" oninput="updateCoverPreview()" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130] focus:outline-none focus:border-[#0078D4]">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label for="coverSemester" class="block text-xs font-semibold text-[#323130] mb-1">Semester</label>
                            <input id="coverSemester" type="text" value="Fall 2026" oninput="saveStudentPrefs(); updateCoverPreview()" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130] focus:outline-none focus:border-[#0078D4]">
                        </div>
                        <div>
                            <label for="coverSubmissionDate" class="block text-xs font-semibold text-[#323130] mb-1">Date of Submission</label>
                            <input id="coverSubmissionDate" type="date" value="{{ date('Y-m-d') }}" oninput="updateCoverPreview()" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130] focus:outline-none focus:border-[#0078D4]">
                        </div>
                    </div>
                </div>

                <!-- 3. Submitted To (Faculty) -->
                <div class="pt-3 border-t border-[#E1DFDD] space-y-3">
                    <div class="text-xs font-bold uppercase tracking-wider text-[#323130]">
                        3. Submitted To (Course Teacher)
                    </div>

                    <div>
                        <label for="coverFacultyPreset" class="block text-xs font-semibold text-[#323130] mb-1">
                            Quick-Fill SWE Faculty Member
                        </label>
                        <select id="coverFacultyPreset" onchange="applyCoverFacultyPreset(this)" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-3 py-1.5 text-xs text-[#323130] focus:outline-none focus:border-[#0078D4]">
                            <option value="">-- Select Faculty Initial / Name --</option>
                            @foreach($facultyDirectory as $init => $fac)
                                <option value="{{ $init }}" data-name="{{ $fac['name'] }}" data-designation="{{ $fac['designation'] }}">
                                    [{{ $init }}] {{ $fac['name'] }} — {{ $fac['designation'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label for="coverTeacherName" class="block text-xs font-semibold text-[#323130] mb-1">Teacher Name</label>
                            <input id="coverTeacherName" type="text" value="Dr. Md. Abdul Kader" oninput="updateCoverPreview()" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130] focus:outline-none focus:border-[#0078D4]">
                        </div>
                        <div>
                            <label for="coverTeacherDesignation" class="block text-xs font-semibold text-[#323130] mb-1">Designation</label>
                            <input id="coverTeacherDesignation" type="text" value="Associate Professor" oninput="updateCoverPreview()" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130] focus:outline-none focus:border-[#0078D4]">
                        </div>
                    </div>
                </div>

                <!-- 4. Submitted By (Individual or Group) -->
                <div class="pt-3 border-t border-[#E1DFDD] space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-[#323130]">
                            4. Submitted By (Student)
                        </span>
                        <div class="inline-flex rounded-[4px] bg-[#F3F2F1] p-0.5 border border-[#E1DFDD] text-[11px] font-semibold">
                            <label class="px-2 py-0.5 rounded-[3px] cursor-pointer has-[:checked]:bg-[#0078D4] has-[:checked]:text-white">
                                <input type="radio" name="submission_mode" value="individual" checked onchange="toggleSubmissionMode()" class="sr-only">
                                Individual
                            </label>
                            <label class="px-2 py-0.5 rounded-[3px] cursor-pointer has-[:checked]:bg-[#0078D4] has-[:checked]:text-white">
                                <input type="radio" name="submission_mode" value="group" onchange="toggleSubmissionMode()" class="sr-only">
                                Group
                            </label>
                        </div>
                    </div>

                    <div id="individualStudentFields" class="space-y-2.5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <div>
                                <label for="coverStudentName" class="block text-xs font-semibold text-[#323130] mb-1">Student Name</label>
                                <input id="coverStudentName" type="text" value="Md. Rakibul Islam" autocomplete="name" oninput="saveStudentPrefs(); updateCoverPreview()" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130] focus:outline-none focus:border-[#0078D4]">
                            </div>
                            <div>
                                <label for="coverStudentId" class="block text-xs font-semibold text-[#323130] mb-1">Student ID</label>
                                <input id="coverStudentId" type="text" value="241-35-001" autocorrect="off" oninput="saveStudentPrefs(); updateCoverPreview()" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs font-mono text-[#323130] focus:outline-none focus:border-[#0078D4]">
                            </div>
                        </div>
                    </div>

                    <div id="groupMembersSection" class="hidden space-y-2">
                        <div id="groupMembersList" class="space-y-2"></div>
                        <button type="button" onclick="addGroupMemberRow()" class="text-xs font-bold text-[#0078D4] hover:underline cursor-pointer">
                            + Add Group Member
                        </button>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                        <div>
                            <label for="coverBatch" class="block text-xs font-semibold text-[#323130] mb-1">Batch</label>
                            <input id="coverBatch" type="text" value="45" oninput="saveStudentPrefs(); updateCoverPreview()" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130] focus:outline-none focus:border-[#0078D4]">
                        </div>
                        <div>
                            <label for="coverSection" class="block text-xs font-semibold text-[#323130] mb-1">Section</label>
                            <input id="coverSection" type="text" value="A" oninput="saveStudentPrefs(); updateCoverPreview()" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs uppercase text-[#323130] focus:outline-none focus:border-[#0078D4]">
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label for="coverProgram" class="block text-xs font-semibold text-[#323130] mb-1">Program</label>
                            <input id="coverProgram" type="text" value="B.Sc. in SWE" oninput="updateCoverPreview()" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130] focus:outline-none focus:border-[#0078D4]">
                        </div>
                    </div>
                </div>

                <!-- 5. Styling & Evaluation Box Options -->
                <div class="pt-3 border-t border-[#E1DFDD] space-y-2.5">
                    <div class="text-xs font-bold uppercase tracking-wider text-[#323130]">
                        5. Style &amp; Print Options
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 items-center">
                        <div>
                            <label for="coverThemeSelect" class="block text-xs font-semibold text-[#323130] mb-1">Color Theme</label>
                            <select id="coverThemeSelect" onchange="updateCoverPreview()" class="w-full bg-white border border-[#E1DFDD] rounded-[4px] px-2.5 py-1.5 text-xs text-[#323130]">
                                <option value="blue">DIU Academic Blue</option>
                                <option value="mono">Formal Monochrome (B&amp;W)</option>
                                <option value="emerald">Academic Emerald</option>
                            </select>
                        </div>
                        <div class="space-y-1.5 pt-2 sm:pt-4">
                            <div class="flex items-center gap-2">
                                <input id="coverIncludeMarks" type="checkbox" checked onchange="updateCoverPreview()" class="accent-[#0078D4]">
                                <label for="coverIncludeMarks" class="text-xs text-[#323130] font-medium cursor-pointer">Include Marks / Evaluation Table</label>
                            </div>
                            <div class="flex items-center gap-2">
                                <input id="coverIncludeBorder" type="checkbox" checked onchange="updateCoverPreview()" class="accent-[#0078D4]">
                                <label for="coverIncludeBorder" class="text-xs text-[#323130] font-medium cursor-pointer">Show Formal A4 Page Frame</label>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 flex items-center justify-between">
                        <button type="button" onclick="resetCoverForm()" class="text-xs font-semibold text-[#605E5C] hover:text-[#A80000] cursor-pointer">
                            Reset Defaults
                        </button>
                        <span class="text-[11px] text-[#605E5C]">Student info saves locally in browser</span>
                    </div>
                </div>
            </form>
        </div>

        <!-- RIGHT COLUMN: REAL-TIME A4 PORTRAIT PREVIEW -->
        <div class="xl:col-span-7 flex flex-col items-center">
            <div class="no-print w-full flex items-center justify-between mb-2 text-xs text-[#605E5C]">
                <span class="font-bold text-[#323130]">Live A4 Portrait Preview (210mm × 297mm)</span>
                <span class="font-mono text-[11px]">Ready for Print / PDF / PNG</span>
            </div>

            <!-- A4 Portrait Sheet Container -->
            <div class="w-full overflow-x-auto pb-2 flex justify-center">
                <div id="coverPageSheet"
                     class="bg-white text-[#1A1A1A] shadow-[0_6.4px_14.4px_0_rgba(0,0,0,0.132)] shrink-0 relative flex flex-col justify-between"
                     style="width: 794px; min-height: 1123px; padding: 48px 54px; font-family: 'Segoe UI', 'Plus Jakarta Sans', Georgia, serif; box-sizing: border-box;">

                    <!-- Outer Page Frame -->
                    <div id="coverOuterFrame" class="flex-1 flex flex-col justify-between p-8 border-2 border-[#0078D4]" style="border-style: double; border-width: 4px;">

                        <!-- Top University Header & Inline SWE Logo -->
                        <div class="text-center space-y-2.5 pt-2">
                            <div class="mx-auto w-28 h-16 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 446 244" fill="none" class="w-full h-full">
                                    <rect id="coverLogoOuter" x="0" y="0" width="446" height="244" rx="122" fill="#3870C0" />
                                    <circle cx="34" cy="122" r="17" fill="#FFFFFF" />
                                    <rect x="94" y="37" width="314" height="170" rx="85" fill="#FFFFFF" />
                                    <polygon id="coverLogoTriangle" points="264,107 251,141 277,141" fill="#3870C0" />
                                    <g stroke="#1A1A1A" stroke-width="10.5" stroke-linecap="butt" stroke-linejoin="round" fill="none">
                                        <path d="M 125 141 L 183 141 A 17 17 0 0 0 183 107 L 141 107 A 16 16 0 0 1 141 75 L 204 75 L 234 141 L 264 75 L 294 141 L 324 75 L 380 75" />
                                        <path d="M 309 107 L 362 107" />
                                        <path d="M 294 141 L 378 141" />
                                    </g>
                                </svg>
                            </div>

                            <h2 id="previewUniversityName" class="text-[24px] font-extrabold tracking-wide uppercase text-[#0078D4]">
                                Daffodil International University
                            </h2>
                            <div class="text-[14px] font-bold text-[#323130] tracking-wide uppercase">
                                Department of Software Engineering (SWE)
                            </div>
                            <div class="text-[12px] font-medium text-[#605E5C]">
                                Faculty of Science &amp; Information Technology • Daffodil Smart City, Ashulia, Dhaka
                            </div>

                            <div id="coverHeaderDivider" class="mx-auto w-48 h-0.5 bg-[#0078D4] mt-3"></div>
                        </div>

                        <!-- Document Type Banner & Course / Topic Box -->
                        <div class="my-6 space-y-6 text-center">
                            <div class="inline-block">
                                <div id="previewCoverBanner" class="px-8 py-2.5 rounded-[4px] bg-[#0078D4] text-white text-[20px] font-black tracking-[0.14em] uppercase">
                                    LAB REPORT
                                </div>
                            </div>

                            <!-- Course Code & Title -->
                            <div class="space-y-1.5">
                                <div class="text-[12px] font-bold uppercase tracking-widest text-[#605E5C]">
                                    Course Code &amp; Title
                                </div>
                                <div id="previewCourseLine" class="text-[18px] font-bold text-[#201F1E]">
                                    SE215: Algorithms Design &amp; Analysis Lab
                                </div>
                            </div>

                            <!-- Experiment / Assignment / Assessment Topic Box -->
                            <div id="previewTopicBox" class="mx-auto max-w-xl rounded-[4px] border border-[#C7E0F4] bg-[#EFF6FC]/60 px-6 py-4 space-y-1.5">
                                <div id="previewDocNumber" class="text-[13px] font-bold uppercase tracking-wider text-[#0078D4]">
                                    Experiment No. 01
                                </div>
                                <div id="previewDocTitle" class="text-[16px] font-bold text-[#201F1E] leading-snug">
                                    Asymptotic Complexity &amp; Divide-and-Conquer Sorting
                                </div>
                            </div>
                        </div>

                        <!-- Optional Evaluation / Marks Table -->
                        <div id="previewMarksSection" class="my-3">
                            <table class="w-full border-collapse text-[11px] text-center">
                                <thead>
                                    <tr id="previewMarksHeader" class="bg-[#F3F2F1] text-[#323130]">
                                        <th class="border border-[#8A8886] py-1.5 px-2 font-bold">Criteria / Evaluation</th>
                                        <th class="border border-[#8A8886] py-1.5 px-2 font-bold w-24">Max Marks</th>
                                        <th class="border border-[#8A8886] py-1.5 px-2 font-bold w-28">Obtained</th>
                                        <th class="border border-[#8A8886] py-1.5 px-2 font-bold w-32">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="border border-[#8A8886] py-2.5 px-2 text-left text-[#605E5C]">Performance / Understanding / Presentation</td>
                                        <td class="border border-[#8A8886] py-2.5 px-2">&nbsp;</td>
                                        <td class="border border-[#8A8886] py-2.5 px-2">&nbsp;</td>
                                        <td class="border border-[#8A8886] py-2.5 px-2">&nbsp;</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Submitted To & Submitted By Grid -->
                        <div class="my-4 grid grid-cols-2 gap-6 items-stretch">
                            <!-- Submitted To -->
                            <div class="border border-[#8A8886] rounded-[4px] p-4 flex flex-col justify-between bg-white">
                                <div>
                                    <div id="previewSubmittedToTitle" class="text-[12px] font-extrabold uppercase tracking-wider text-[#0078D4] border-b border-[#E1DFDD] pb-1.5 mb-2.5">
                                        Submitted To
                                    </div>
                                    <div id="previewTeacherName" class="text-[15px] font-bold text-[#201F1E]">
                                        Dr. Md. Abdul Kader
                                    </div>
                                    <div id="previewTeacherDesignation" class="text-[12.5px] font-semibold text-[#323130] mt-0.5">
                                        Associate Professor
                                    </div>
                                    <div class="text-[11.5px] text-[#605E5C] mt-1">
                                        Department of Software Engineering<br>
                                        Daffodil International University
                                    </div>
                                </div>
                            </div>

                            <!-- Submitted By -->
                            <div class="border border-[#8A8886] rounded-[4px] p-4 flex flex-col justify-between bg-white">
                                <div>
                                    <div id="previewSubmittedByTitle" class="text-[12px] font-extrabold uppercase tracking-wider text-[#0078D4] border-b border-[#E1DFDD] pb-1.5 mb-2.5">
                                        Submitted By
                                    </div>
                                    <div id="previewIndividualBlock" class="space-y-1 text-[12.5px]">
                                        <div><span class="font-semibold text-[#605E5C]">Name:</span> <strong id="previewStudentName" class="text-[#201F1E]">Md. Rakibul Islam</strong></div>
                                        <div><span class="font-semibold text-[#605E5C]">ID:</span> <strong id="previewStudentId" class="font-mono text-[#201F1E]">241-35-001</strong></div>
                                        <div><span class="font-semibold text-[#605E5C]">Batch &amp; Sec:</span> <strong id="previewBatchSec" class="text-[#201F1E]">45 (Section A)</strong></div>
                                        <div><span class="font-semibold text-[#605E5C]">Program:</span> <span id="previewProgram" class="text-[#323130]">B.Sc. in SWE</span></div>
                                        <div><span class="font-semibold text-[#605E5C]">Semester:</span> <span id="previewSemester" class="text-[#323130]">Fall 2026</span></div>
                                    </div>
                                    <div id="previewGroupBlock" class="hidden space-y-1.5 text-[11px]">
                                        <table class="w-full border-collapse text-left">
                                            <thead>
                                                <tr class="bg-[#F3F2F1]">
                                                    <th class="border border-[#D2D0CE] px-1.5 py-1">Name</th>
                                                    <th class="border border-[#D2D0CE] px-1.5 py-1">Student ID</th>
                                                </tr>
                                            </thead>
                                            <tbody id="previewGroupRows"></tbody>
                                        </table>
                                        <div class="text-[11px] text-[#323130] pt-1">
                                            <strong>Batch &amp; Sec:</strong> <span id="previewGroupBatchSec">45 (Section A)</span> • <span id="previewGroupSemester">Fall 2026</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Footer: Submission Date & Signature -->
                        <div class="pt-6 mt-2 border-t border-[#E1DFDD] flex items-end justify-between text-[12px]">
                            <div>
                                <span class="font-semibold text-[#605E5C]">Date of Submission:</span>
                                <strong id="previewSubmissionDate" class="text-[#201F1E] ml-1">{{ date('d M Y') }}</strong>
                            </div>
                            <div class="text-center">
                                <div class="w-48 border-t border-[#323130] pt-1 text-[11px] font-semibold text-[#323130]">
                                    Course Teacher's Signature
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

