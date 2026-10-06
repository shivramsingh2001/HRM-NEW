/**
 * Monthly salary breakdown from an annual CTC.
 *
 * Same rules as the employee wizard's Payroll step (view-user.blade.php →
 * calculateSalaryBreakdown): a payroll structure's calculator components
 * (PayrollStructure::calculatorComponents(), the option's data-calc JSON)
 * drive the split; with no structure the defaults are basic 50% of CTC,
 * HRA 40% of basic, conveyance 1600, medical 1250, PF 12% (basic capped at
 * 15000), ESI 1.75% / 3.25% (only up to 21000 gross) and PT 200.
 *
 * Returns monthly amounts keyed by the flat field names the server expects
 * (UserController::updateStep6), plus totals for display.
 */
window.computeSalaryBreakdown = function (annualCTC, calc) {
    calc = calc || {};
    const monthlyCTC = (parseFloat(annualCTC) || 0) / 12;

    // structure component code -> flat field name
    const ALLOWANCES = {
        hra: 'hra',
        conveyance: 'conveyence',
        medical_allowance: 'medical_allowance',
        children_allowance: 'children_allowance',
        post_allowance: 'post_allowance',
        leave_travel_allowance: 'leave_travel_allowance',
        monthly_incentive: 'monthly_incentive',
    };

    let pctSum = 0;
    let fixedSum = 0;
    const pct = {};
    const fixed = {};
    let custom = false;

    Object.keys(ALLOWANCES).forEach(function (code) {
        const entry = calc[code];
        const field = ALLOWANCES[code];
        if (entry && entry.type === 'percentage' && entry.base === 'basic') {
            pct[field] = entry.value;
            pctSum += entry.value;
            custom = true;
        } else if (entry && entry.type === 'fixed') {
            fixed[field] = entry.value;
            fixedSum += entry.value;
            custom = true;
        }
    });

    const out = {};
    let basic;

    if (custom) {
        basic = Math.max(0, (monthlyCTC - fixedSum) / (1 + (pctSum / 100)));
        Object.keys(ALLOWANCES).forEach(function (code) {
            const field = ALLOWANCES[code];
            out[field] = fixed[field] !== undefined ? fixed[field] : basic * ((pct[field] || 0) / 100);
        });
    } else {
        basic = monthlyCTC * 0.5;
        out.hra = basic * 0.4;
        out.conveyence = 1600;
        out.medical_allowance = 1250;
        out.children_allowance = 0;
        out.post_allowance = 0;
        out.leave_travel_allowance = 0;
        out.monthly_incentive = 0;
    }

    const totalAllowances = Object.keys(ALLOWANCES).reduce(function (sum, code) {
        return sum + out[ALLOWANCES[code]];
    }, 0);
    const gross = basic + totalAllowances;

    function percentageDeduction(entry, base, defaultPct, defaultCeiling, defaultRule) {
        let rate = defaultPct;
        let ceiling = defaultCeiling;
        let rule = defaultRule;

        if (entry && entry.type === 'fixed') {
            return entry.value;
        }
        if (entry && entry.type === 'percentage') {
            rate = entry.value;
            ceiling = entry.ceiling_amount != null ? entry.ceiling_amount : null;
            rule = entry.ceiling_rule || null;
        }
        if (!rate || rate <= 0) {
            return 0;
        }
        if (rule === 'ceiling_exclude' && ceiling && base > ceiling) {
            return 0;
        }
        const effectiveBase = (rule === 'cap_base_before_percentage' && ceiling) ? Math.min(base, ceiling) : base;

        return effectiveBase * (rate / 100);
    }

    out.basic_salary = basic;
    out.special_allowance = 0;
    out.provident_fund = percentageDeduction(calc.pf_employee, basic, 12, 15000, 'cap_base_before_percentage');
    out.employer_provident_fund = percentageDeduction(calc.pf_employer, basic, 12, 15000, 'cap_base_before_percentage');
    out.esi = percentageDeduction(calc.esi_employee, gross, 1.75, 21000, 'ceiling_exclude');
    out.employer_esi = percentageDeduction(calc.esi_employer, gross, 3.25, 21000, 'ceiling_exclude');

    if (calc.pt && calc.pt.type === 'fixed') {
        out.professional_tax = calc.pt.value;
    } else if (calc.pt && calc.pt.type === 'percentage') {
        out.professional_tax = gross * (calc.pt.value / 100);
    } else {
        out.professional_tax = 200;
    }

    out.total_allowances = totalAllowances;
    out.gross_salary = gross;
    out.total_deductions = out.provident_fund + out.esi + out.professional_tax;
    out.net_salary = gross - out.total_deductions;
    out.monthly_ctc = gross + out.employer_provident_fund + out.employer_esi;

    return out;
};
