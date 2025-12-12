<?php
/**
 * ✅ MODEL: BÁO CÁO DOANH SỐ NHÂN VIÊN
 * Điều chỉnh từ OrderModel.php cũ để phù hợp database GKHL
 */

require_once 'config/database.php';

class NhanVienReportModel {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * ✅ Lấy danh sách tháng có sẵn
     */
    public function getAvailableMonths() {
        try {
            $sql = "SELECT DISTINCT CONCAT(RptYear, '-', LPAD(RptMonth, 2, '0')) as thang
                    FROM orderdetail
                    WHERE RptYear IS NOT NULL AND RptMonth IS NOT NULL
                    AND RptYear >= 2020
                    ORDER BY RptYear DESC, RptMonth DESC
                    LIMIT 24";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Exception $e) {
            error_log("getAvailableMonths error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ✅ Tổng doanh số theo tháng (RptYear-RptMonth)
     */
    public function getTotalByMonth($thang) {
        try {
            list($year, $month) = explode('-', $thang);
            
            $sql = "SELECT COALESCE(SUM(TotalNetAmount), 0) as total
                    FROM orderdetail
                    WHERE RptYear = ? AND RptMonth = ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$year, $month]);
            return floatval($stmt->fetch()['total'] ?? 0);
        } catch (Exception $e) {
            error_log("getTotalByMonth error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Tổng doanh số theo khoảng ngày (OrderDate)
     */
    public function getTotalByDateRange($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT COALESCE(SUM(TotalNetAmount), 0) as total
                    FROM orderdetail
                    WHERE DATE(OrderDate) >= ? AND DATE(OrderDate) <= ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay]);
            return floatval($stmt->fetch()['total'] ?? 0);
        } catch (Exception $e) {
            error_log("getTotalByDateRange error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Doanh số nhân viên theo tháng
     */
    public function getEmployeeTotalByMonth($dsr_code, $thang) {
        try {
            list($year, $month) = explode('-', $thang);
            
            $sql = "SELECT COALESCE(SUM(TotalNetAmount), 0) as total
                    FROM orderdetail
                    WHERE DSRCode = ? AND RptYear = ? AND RptMonth = ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$dsr_code, $year, $month]);
            return floatval($stmt->fetch()['total'] ?? 0);
        } catch (Exception $e) {
            error_log("getEmployeeTotalByMonth error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Doanh số nhân viên theo khoảng ngày
     */
    public function getEmployeeTotalByDateRange($dsr_code, $tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT COALESCE(SUM(TotalNetAmount), 0) as total
                    FROM orderdetail
                    WHERE DSRCode = ?
                    AND DATE(OrderDate) >= ? AND DATE(OrderDate) <= ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$dsr_code, $tu_ngay, $den_ngay]);
            return floatval($stmt->fetch()['total'] ?? 0);
        } catch (Exception $e) {
            error_log("getEmployeeTotalByDateRange error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Số ngày có doanh số
     */
    public function getEmployeeDaysWithOrders($dsr_code, $tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT COUNT(DISTINCT DATE(OrderDate)) as days_count
                    FROM orderdetail
                    WHERE DSRCode = ?
                    AND DATE(OrderDate) >= ? AND DATE(OrderDate) <= ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$dsr_code, $tu_ngay, $den_ngay]);
            return intval($stmt->fetch()['days_count'] ?? 0);
        } catch (Exception $e) {
            error_log("getEmployeeDaysWithOrders error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Doanh số ngày cao nhất (khoảng)
     */
    public function getMaxDailyAmountByDateRange($dsr_code, $tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT MAX(daily_total) as max_daily
                    FROM (
                        SELECT SUM(TotalNetAmount) as daily_total
                        FROM orderdetail
                        WHERE DSRCode = ?
                        AND DATE(OrderDate) >= ? AND DATE(OrderDate) <= ?
                        GROUP BY DATE(OrderDate)
                    ) daily_stats";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$dsr_code, $tu_ngay, $den_ngay]);
            return floatval($stmt->fetch()['max_daily'] ?? 0);
        } catch (Exception $e) {
            error_log("getMaxDailyAmountByDateRange error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Doanh số ngày cao nhất (tháng)
     */
    public function getMaxDailyAmountByMonth($dsr_code, $thang) {
        try {
            list($year, $month) = explode('-', $thang);
            
            $sql = "SELECT MAX(daily_total) as max_daily
                    FROM (
                        SELECT SUM(TotalNetAmount) as daily_total
                        FROM orderdetail
                        WHERE DSRCode = ? AND RptYear = ? AND RptMonth = ?
                        GROUP BY DATE(OrderDate)
                    ) daily_stats";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$dsr_code, $year, $month]);
            return floatval($stmt->fetch()['max_daily'] ?? 0);
        } catch (Exception $e) {
            error_log("getMaxDailyAmountByMonth error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Số nhân viên trong khoảng
     */
    public function getEmployeeCountInRange($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT COUNT(DISTINCT DSRCode) as emp_count
                    FROM orderdetail
                    WHERE DATE(OrderDate) >= ? AND DATE(OrderDate) <= ?
                    AND DSRCode IS NOT NULL AND DSRCode != ''";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay]);
            return intval($stmt->fetch()['emp_count'] ?? 0);
        } catch (Exception $e) {
            error_log("getEmployeeCountInRange error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Số nhân viên trong tháng
     */
    public function getEmployeeCountInMonth($thang) {
        try {
            list($year, $month) = explode('-', $thang);
            
            $sql = "SELECT COUNT(DISTINCT DSRCode) as emp_count
                    FROM orderdetail
                    WHERE RptYear = ? AND RptMonth = ?
                    AND DSRCode IS NOT NULL AND DSRCode != ''";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$year, $month]);
            return intval($stmt->fetch()['emp_count'] ?? 0);
        } catch (Exception $e) {
            error_log("getEmployeeCountInMonth error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Trung bình doanh số/ngày/nhân viên (khoảng)
     */
    public function getSystemRangeAveragePerDay($tu_ngay, $den_ngay) {
        try {
            $total = $this->getTotalByDateRange($tu_ngay, $den_ngay);
            $emp_count = $this->getEmployeeCountInRange($tu_ngay, $den_ngay);
            
            $ngay_diff = intval((strtotime($den_ngay) - strtotime($tu_ngay)) / 86400);
            $so_ngay = max(1, $ngay_diff + 1);
            
            if ($emp_count > 0 && $so_ngay > 0) {
                return floatval($total / $so_ngay / $emp_count);
            }
            return 0;
        } catch (Exception $e) {
            error_log("getSystemRangeAveragePerDay error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Trung bình doanh số/ngày/nhân viên (tháng)
     */
    public function getSystemMonthlyAveragePerDay($thang) {
        try {
            $total = $this->getTotalByMonth($thang);
            $emp_count = $this->getEmployeeCountInMonth($thang);
            
            $so_ngay = intval(date('t', strtotime($thang . '-01')));
            
            if ($emp_count > 0 && $so_ngay > 0) {
                return floatval($total / $so_ngay / $emp_count);
            }
            return 0;
        } catch (Exception $e) {
            error_log("getSystemMonthlyAveragePerDay error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ TB doanh số ngày cao nhất/nhân viên (khoảng)
     */
    public function getSystemMaxDailyAveragePerEmployee($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT SUM(max_daily) as total_max, COUNT(*) as emp_count
                    FROM (
                        SELECT MAX(daily_total) as max_daily
                        FROM (
                            SELECT SUM(TotalNetAmount) as daily_total
                            FROM orderdetail
                            WHERE DATE(OrderDate) >= ? AND DATE(OrderDate) <= ?
                            AND DSRCode IS NOT NULL AND DSRCode != ''
                            GROUP BY DSRCode, DATE(OrderDate)
                        ) daily_by_emp
                        GROUP BY DSRCode
                    ) emp_max";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay]);
            $result = $stmt->fetch();
            
            $total_max = floatval($result['total_max'] ?? 0);
            $emp_count = intval($result['emp_count'] ?? 0);
            
            return ($emp_count > 0) ? ($total_max / $emp_count) : 0;
        } catch (Exception $e) {
            error_log("getSystemMaxDailyAveragePerEmployee error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ TB doanh số ngày cao nhất/nhân viên (tháng)
     */
    public function getSystemMaxDailyAveragePerEmployeeByMonth($thang) {
        try {
            list($year, $month) = explode('-', $thang);
            
            $sql = "SELECT SUM(max_daily) as total_max, COUNT(*) as emp_count
                    FROM (
                        SELECT MAX(daily_total) as max_daily
                        FROM (
                            SELECT SUM(TotalNetAmount) as daily_total
                            FROM orderdetail
                            WHERE RptYear = ? AND RptMonth = ?
                            AND DSRCode IS NOT NULL AND DSRCode != ''
                            GROUP BY DSRCode, DATE(OrderDate)
                        ) daily_by_emp
                        GROUP BY DSRCode
                    ) emp_max";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$year, $month]);
            $result = $stmt->fetch();
            
            $total_max = floatval($result['total_max'] ?? 0);
            $emp_count = intval($result['emp_count'] ?? 0);
            
            return ($emp_count > 0) ? ($total_max / $emp_count) : 0;
        } catch (Exception $e) {
            error_log("getSystemMaxDailyAveragePerEmployeeByMonth error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ✅ Lấy danh sách nhân viên (DSRCode duy nhất)
     */
    public function getAllEmployees() {
        try {
            $sql = "SELECT DISTINCT 
                        o.DSRCode,
                        o.DSRTypeProvince,
                        CONCAT('NV_', o.DSRCode) as ten_nv
                    FROM orderdetail o
                    WHERE o.DSRCode IS NOT NULL 
                    AND o.DSRCode != ''
                    ORDER BY o.DSRCode";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("getAllEmployees error: " . $e->getMessage());
            return [];
        }
    }
}