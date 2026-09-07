<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\EmployeeAssignmentController;
use App\Models\Employee;
use App\Models\OrgChartNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OrgChartNodeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_id'              => 'nullable|exists:org_chart_nodes,id',
            'node_type'              => 'required|in:department,brand,outlet,employee',
            'department_id'          => 'nullable|exists:departments,id',
            'outlet_id'              => 'nullable|exists:outlets,id',
            'brand_name'             => 'nullable|string|max:100',
            'employee_id'            => 'nullable|exists:employees,id',
            'employee_position_id'   => 'nullable|exists:positions,id',
            'employee_department_id' => 'nullable|exists:departments,id',
            'is_leader_override'     => 'nullable|boolean',
        ]);

        $error = $this->validateTypePayload($data);
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        if ($data['node_type'] === OrgChartNode::TYPE_EMPLOYEE) {
            if (OrgChartNode::where('employee_id', $data['employee_id'])->exists()) {
                return response()->json(['message' => 'Karyawan ini sudah ada di tempat lain pada struktur.'], 422);
            }
            $this->applyEmployeeFields($data);
        }

        $node = OrgChartNode::create([
            'parent_id'           => $data['parent_id'] ?? null,
            'node_type'           => $data['node_type'],
            'department_id'       => $data['node_type'] === OrgChartNode::TYPE_DEPARTMENT ? $data['department_id'] : null,
            'outlet_id'           => $data['node_type'] === OrgChartNode::TYPE_OUTLET ? $data['outlet_id'] : null,
            'brand_name'          => $data['node_type'] === OrgChartNode::TYPE_BRAND ? $data['brand_name'] : null,
            'employee_id'         => $data['node_type'] === OrgChartNode::TYPE_EMPLOYEE ? $data['employee_id'] : null,
            'is_leader_override'  => $data['is_leader_override'] ?? null,
            'created_by'          => auth()->id(),
        ]);

        if ($node->node_type === OrgChartNode::TYPE_EMPLOYEE) {
            $this->syncEmployeeOutletFromAncestry($node);
        }

        return response()->json(['message' => 'Node berhasil ditambahkan.', 'id' => $node->id]);
    }

    public function update(Request $request, OrgChartNode $orgChartNode): JsonResponse
    {
        $data = $request->validate([
            'department_id'          => 'nullable|exists:departments,id',
            'outlet_id'              => 'nullable|exists:outlets,id',
            'brand_name'             => 'nullable|string|max:100',
            'employee_id'            => 'nullable|exists:employees,id',
            'employee_position_id'   => 'nullable|exists:positions,id',
            'employee_department_id' => 'nullable|exists:departments,id',
            'is_leader_override'     => 'nullable|boolean',
        ]);

        if ($orgChartNode->node_type === OrgChartNode::TYPE_EMPLOYEE && ! empty($data['employee_id'])) {
            $usedByOther = OrgChartNode::where('employee_id', $data['employee_id'])
                ->where('id', '!=', $orgChartNode->id)
                ->exists();
            if ($usedByOther) {
                return response()->json(['message' => 'Karyawan ini sudah ada di tempat lain pada struktur.'], 422);
            }
            $this->applyEmployeeFields($data);
            $orgChartNode->employee_id = $data['employee_id'];
        }

        if ($orgChartNode->node_type === OrgChartNode::TYPE_EMPLOYEE && array_key_exists('is_leader_override', $data)) {
            $orgChartNode->is_leader_override = $data['is_leader_override'];
        }

        if ($orgChartNode->node_type === OrgChartNode::TYPE_DEPARTMENT && ! empty($data['department_id'])) {
            $orgChartNode->department_id = $data['department_id'];
        }

        if ($orgChartNode->node_type === OrgChartNode::TYPE_OUTLET && ! empty($data['outlet_id'])) {
            $orgChartNode->outlet_id = $data['outlet_id'];
        }

        if ($orgChartNode->node_type === OrgChartNode::TYPE_BRAND && ! empty($data['brand_name'])) {
            $orgChartNode->brand_name = $data['brand_name'];
        }

        // employee_position_id / employee_department_id juga berlaku saat
        // mengedit node yang employee_id-nya tidak berubah (misal cuma mau
        // ganti jabatan orang yang sudah ada di node ini).
        if ($orgChartNode->node_type === OrgChartNode::TYPE_EMPLOYEE && empty($data['employee_id']) && $orgChartNode->employee_id) {
            $this->applyEmployeeFields($data, $orgChartNode->employee_id);
        }

        $orgChartNode->save();

        return response()->json(['message' => 'Node berhasil diperbarui.']);
    }

    public function destroy(OrgChartNode $orgChartNode): JsonResponse
    {
        if ($orgChartNode->children()->exists()) {
            return response()->json(['message' => 'Node ini masih punya bawahan/anak node, pindahkan atau hapus dulu sebelum menghapus node ini.'], 422);
        }

        $orgChartNode->delete();

        return response()->json(['message' => 'Node berhasil dihapus.']);
    }

    public function setParent(Request $request, OrgChartNode $orgChartNode): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => 'nullable|exists:org_chart_nodes,id',
        ]);

        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            if ((int) $parentId === $orgChartNode->id) {
                return response()->json(['message' => 'Node tidak bisa menjadi bawahan dirinya sendiri.'], 422);
            }
            if ($this->wouldCreateNodeCycle($orgChartNode, (int) $parentId)) {
                return response()->json(['message' => 'Perubahan ini akan membentuk rantai struktur melingkar.'], 422);
            }
        }

        $orgChartNode->update(['parent_id' => $parentId]);

        if ($orgChartNode->node_type === OrgChartNode::TYPE_EMPLOYEE) {
            $this->syncEmployeeOutletFromAncestry($orgChartNode);
        }

        return response()->json(['message' => 'Struktur berhasil diperbarui.']);
    }

    /**
     * Kalau node karyawan ini (langsung atau lewat leluhurnya) sekarang
     * berada di bawah node tipe outlet, sinkronkan employees.outlet_id +
     * employee_outlet_assignments supaya menaruh seseorang di bawah cabang
     * Outlet tertentu di canvas builder benar-benar berarti dia ditugaskan
     * ke outlet itu — bukan cuma gambar tanpa efek ke data asli.
     */
    private function syncEmployeeOutletFromAncestry(OrgChartNode $node): void
    {
        if (! $node->employee_id) {
            return;
        }

        $currentId = $node->parent_id;
        $hops      = 0;

        while ($currentId !== null && $hops < 30) {
            $ancestor = OrgChartNode::find($currentId);
            if (! $ancestor) {
                return;
            }
            if ($ancestor->node_type === OrgChartNode::TYPE_OUTLET && $ancestor->outlet_id) {
                $employee = Employee::find($node->employee_id);
                if ($employee && $employee->outlet_id !== $ancestor->outlet_id) {
                    app(EmployeeAssignmentController::class)
                        ->reassignOutletFor($employee, $ancestor->outlet_id, 'Org-chart builder');
                }
                return;
            }
            $currentId = $ancestor->parent_id;
            $hops++;
        }
    }

    private function validateTypePayload(array $data): ?string
    {
        return match ($data['node_type']) {
            OrgChartNode::TYPE_DEPARTMENT => empty($data['department_id']) ? 'Pilih departemen terlebih dahulu.' : null,
            OrgChartNode::TYPE_OUTLET     => empty($data['outlet_id']) ? 'Pilih outlet terlebih dahulu.' : null,
            OrgChartNode::TYPE_BRAND      => empty($data['brand_name']) ? 'Isi nama brand terlebih dahulu.' : null,
            OrgChartNode::TYPE_EMPLOYEE   => empty($data['employee_id']) ? 'Pilih karyawan terlebih dahulu.' : null,
            default => 'Tipe node tidak dikenali.',
        };
    }

    /**
     * Set employees.position_id / employees.department_id dari menu "+"
     * ("Pilih / Buat Posisi", "Pilih / Buat Departemen") — Posisi & Departemen
     * TETAP disimpan hanya di tabel employees (satu sumber kebenaran), node
     * org-chart tidak menyimpan salinan nama jabatan/departemen sendiri.
     */
    private function applyEmployeeFields(array $data, ?int $employeeIdOverride = null): void
    {
        $employeeId = $employeeIdOverride ?? $data['employee_id'];
        $updates    = [];

        if (! empty($data['employee_position_id'])) {
            $updates['position_id'] = $data['employee_position_id'];
        }
        if (! empty($data['employee_department_id'])) {
            $updates['department_id'] = $data['employee_department_id'];
        }

        if (! empty($updates)) {
            Employee::where('id', $employeeId)->update($updates);
        }
    }

    private function wouldCreateNodeCycle(OrgChartNode $node, int $candidateParentId): bool
    {
        $currentId = $candidateParentId;
        $hops      = 0;

        while ($currentId !== null && $hops < 30) {
            if ($currentId === $node->id) {
                return true;
            }
            $currentId = OrgChartNode::where('id', $currentId)->value('parent_id');
            $hops++;
        }

        return false;
    }
}
