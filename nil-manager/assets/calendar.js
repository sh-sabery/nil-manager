// Loaded once, only when a report calendar is opened.
export const openCalendar=(()=>{
    const style=document.createElement('style');
    style.textContent=`
#nil-calendar{width:min(360px,calc(100% - 24px));max-height:calc(100% - 24px);padding:16px;border:1px solid var(--line);border-radius:16px;color:var(--ink)}#nil-calendar::backdrop{background:#17233380}#nil-calendar .nil-section-head{flex-direction:row;align-items:center;margin-bottom:10px}#nil-calendar h3{margin:0}
.nil-calendar-nav,.nil-calendar-grid{display:grid;gap:4px;align-items:center}.nil-calendar-nav{grid-template-columns:auto minmax(0,1fr) 4.4rem auto;margin-bottom:10px}.nil-calendar-grid{grid-template-columns:repeat(7,minmax(0,1fr));text-align:center;margin-bottom:8px}.nil-calendar-grid abbr{text-decoration:none}#nil-calendar .nil-calendar-grid .button{padding:0;min-height:34px}#nil-calendar [aria-current=date]{border-color:var(--nil-accent)}
`;
    document.head.append(style);
    const $=id=>document.getElementById('nil-'+id);
    const calendar=$('calendar'), monthInput=$('calendar-month'), yearInput=$('calendar-year'), days=$('calendar-days');
    // Same Nowruz break (1210) as NIL_Dates within the supported 1200–1499 range.
    // 1200/01/01 was Wednesday, index 4 in a Saturday-first week.
    function leapCount(year){
        const n=year-(year<1210?1181:1210);
        return (year<1210?0:7)+Math.floor(n/33)*8+Math.floor((n%33+3)/4);
    }
    const monthLength=(y,m)=>m<7?31:m<12?30:29+leapCount(y+1)-leapCount(y);
    const ascii=value=>value.replace(/[۰-۹٠-٩]/g,c=>c.charCodeAt(0)%16);
    const persian=value=>String(value).replace(/\d/g,c=>'۰۱۲۳۴۵۶۷۸۹'[c]);
    const calendarDate=(y,m,d)=>persian(y+'/'+String(m).padStart(2,'0')+'/'+String(d).padStart(2,'0'));
    function parseCalendar(value){
        const parts=ascii(value.trim()).match(/^(1[2-4]\d{2})[/-](\d{1,2})[/-](\d{1,2})$/);
        if(!parts)return null;
        const [y,m,d]=parts.slice(1).map(Number);
        return m>=1&&m<=12&&d>=1&&d<=monthLength(y,m)?[y,m,d]:null;
    }
    let dateField,year,month,currentDate;
    function renderCalendar(){
        const start=(4+365*(year-1200)+leapCount(year)-5+(month-1)*30+Math.min(month-1,6))%7;
        const [cy,cm,cd]=currentDate, selected=parseCalendar(dateField.value)||[];
        monthInput.value=String(month);yearInput.value=persian(year);
        $('calendar-prev').disabled=year===1200&&month===1;
        $('calendar-next').disabled=year===1499&&month===12;
        days.innerHTML='<span></span>'.repeat(start)+Array.from({length:monthLength(year,month)},(_,i)=>{
            const d=i+1, active=year===selected[0]&&month===selected[1]&&d===selected[2];
            return `<button type="button" class="button" data-day="${d}" aria-label="${calendarDate(year,month,d)}" aria-pressed="${active}" ${year===cy&&month===cm&&d===cd?'aria-current="date"':''}>${persian(d)}</button>`;
        }).join('');
    }
    function selectDate(y,m,d){dateField.value=calendarDate(y,m,d);calendar.close();}
    function stepMonth(step){
        month+=step;
        if(month===0){year--;month=12;}else if(month===13){year++;month=1;}
        renderCalendar();
    }
    $('calendar-prev').onclick=()=>stepMonth(-1);$('calendar-next').onclick=()=>stepMonth(1);
    monthInput.onchange=()=>{month=Number(monthInput.value);renderCalendar();};
    yearInput.onchange=()=>{
        const y=Number(ascii(yearInput.value));if(Number.isInteger(y)&&y>=1200&&y<=1499)year=y;
        renderCalendar();
    };
    days.onclick=e=>{const d=Number(e.target.dataset.day);if(d)selectDate(year,month,d);};
    $('calendar-today').onclick=()=>selectDate(...currentDate);
    return (field,today)=>{
        dateField=field;
        currentDate=parseCalendar(today);
        [year,month]=parseCalendar(field.value)||currentDate;
        renderCalendar();
        calendar.showModal();
    };
})();
