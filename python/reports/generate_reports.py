import mysql.connector
from datetime import datetime
from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import inch
import sys

# Database connection
db = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="edurole"
)
cursor = db.cursor(dictionary=True)

def generate_student_report(student_id, output_file):
    """Generate a PDF transcript for a student"""
    
    # Get student info
    cursor.execute("SELECT full_name, email, phone FROM users WHERE user_id = %s", (student_id,))
    student = cursor.fetchone()
    
    # Get grades with subjects
    cursor.execute("""
        SELECT s.subject_name, g.assessment_type, g.assessment_name, 
               g.marks_obtained, g.total_marks, g.grade_date,
               ROUND((g.marks_obtained / g.total_marks) * 100, 1) as percentage
        FROM grades g
        JOIN subjects s ON g.subject_id = s.subject_id
        WHERE g.student_id = %s
        ORDER BY g.grade_date DESC
    """, (student_id,))
    grades = cursor.fetchall()
    
    # Calculate averages per subject
    cursor.execute("""
        SELECT s.subject_name, 
               AVG((g.marks_obtained / g.total_marks) * 100) as avg_percentage
        FROM grades g
        JOIN subjects s ON g.subject_id = s.subject_id
        WHERE g.student_id = %s
        GROUP BY s.subject_id
    """, (student_id,))
    subject_averages = cursor.fetchall()
    
    # Calculate overall average
    cursor.execute("""
        SELECT AVG((marks_obtained / total_marks) * 100) as overall_avg
        FROM grades
        WHERE student_id = %s
    """, (student_id,))
    overall = cursor.fetchone()
    
    # Create PDF
    doc = SimpleDocTemplate(output_file, pagesize=A4)
    styles = getSampleStyleSheet()
    story = []
    
    # Title
    title_style = ParagraphStyle('CustomTitle', parent=styles['Heading1'], fontSize=24, textColor=colors.HexColor('#1a73e8'), alignment=1)
    story.append(Paragraph("EDUROLE ACADEMIC TRANSCRIPT", title_style))
    story.append(Spacer(1, 0.3 * inch))
    
    # Student info
    info_style = ParagraphStyle('Info', parent=styles['Normal'], fontSize=12)
    story.append(Paragraph(f"<b>Student Name:</b> {student['full_name']}", info_style))
    story.append(Paragraph(f"<b>Email:</b> {student['email']}", info_style))
    story.append(Paragraph(f"<b>Phone:</b> {student['phone'] or 'N/A'}", info_style))
    story.append(Paragraph(f"<b>Report Date:</b> {datetime.now().strftime('%d %B %Y')}", info_style))
    story.append(Spacer(1, 0.3 * inch))
    
    # Grades table
    if grades:
        grade_data = [['Subject', 'Assessment', 'Marks', 'Percentage']]
        for g in grades:
            grade_data.append([
                g['subject_name'],
                f"{g['assessment_type']}: {g['assessment_name']}",
                f"{g['marks_obtained']}/{g['total_marks']}",
                f"{g['percentage']}%"
            ])
        
        grade_table = Table(grade_data, colWidths=[2*inch, 2*inch, 1.2*inch, 1.2*inch])
        grade_table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#1a73e8')),
            ('TEXTCOLOR', (0, 0), (-1, 0), colors.whitesmoke),
            ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
            ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, 0), 12),
            ('BOTTOMPADDING', (0, 0), (-1, 0), 12),
            ('BACKGROUND', (0, 1), (-1, -1), colors.beige),
            ('GRID', (0, 0), (-1, -1), 1, colors.black),
        ]))
        story.append(grade_table)
        story.append(Spacer(1, 0.3 * inch))
    
    # Subject averages
    if subject_averages:
        story.append(Paragraph("<b>Subject Performance Summary</b>", styles['Heading2']))
        avg_data = [['Subject', 'Average Score']]
        for sa in subject_averages:
            avg_data.append([sa['subject_name'], f"{round(sa['avg_percentage'], 1)}%"])
        
        avg_table = Table(avg_data, colWidths=[3*inch, 2*inch])
        avg_table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#34a853')),
            ('TEXTCOLOR', (0, 0), (-1, 0), colors.whitesmoke),
            ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
            ('GRID', (0, 0), (-1, -1), 1, colors.black),
        ]))
        story.append(avg_table)
        story.append(Spacer(1, 0.2 * inch))
    
    # Overall average
    if overall and overall['overall_avg']:
        overall_avg = round(overall['overall_avg'], 1)
        if overall_avg >= 90:
            grade_letter = 'A+'
        elif overall_avg >= 80:
            grade_letter = 'A'
        elif overall_avg >= 70:
            grade_letter = 'B'
        elif overall_avg >= 60:
            grade_letter = 'C'
        elif overall_avg >= 50:
            grade_letter = 'D'
        else:
            grade_letter = 'F'
        
        story.append(Paragraph(f"<b>Overall Average:</b> {overall_avg}%", styles['Normal']))
        story.append(Paragraph(f"<b>Final Grade:</b> {grade_letter}", styles['Normal']))
    
    # Build PDF
    doc.build(story)
    return output_file

def generate_attendance_summary(class_id, output_file):
    """Generate attendance summary for a class"""
    
    cursor.execute("SELECT class_name FROM classes WHERE class_id = %s", (class_id,))
    class_info = cursor.fetchone()
    
    cursor.execute("""
        SELECT u.full_name, 
               COUNT(a.attendance_id) as total_days,
               SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
               SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
               SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days
        FROM users u
        JOIN attendance a ON u.user_id = a.student_id
        WHERE a.class_id = %s
        GROUP BY u.user_id
        ORDER BY u.full_name
    """, (class_id,))
    attendance_data = cursor.fetchall()
    
    doc = SimpleDocTemplate(output_file, pagesize=A4)
    styles = getSampleStyleSheet()
    story = []
    
    # Title
    title_style = ParagraphStyle('CustomTitle', parent=styles['Heading1'], fontSize=24, textColor=colors.HexColor('#1a73e8'), alignment=1)
    story.append(Paragraph("EDUROLE ATTENDANCE REPORT", title_style))
    story.append(Spacer(1, 0.3 * inch))
    story.append(Paragraph(f"<b>Class:</b> {class_info['class_name']}", styles['Normal']))
    story.append(Paragraph(f"<b>Generated:</b> {datetime.now().strftime('%d %B %Y')}", styles['Normal']))
    story.append(Spacer(1, 0.3 * inch))
    
    if attendance_data:
        att_data = [['Student Name', 'Total Days', 'Present', 'Absent', 'Late', 'Attendance %']]
        for a in attendance_data:
            attendance_pct = round((a['present_days'] / a['total_days']) * 100, 1) if a['total_days'] > 0 else 0
            att_data.append([
                a['full_name'],
                str(a['total_days']),
                str(a['present_days']),
                str(a['absent_days']),
                str(a['late_days']),
                f"{attendance_pct}%"
            ])
        
        att_table = Table(att_data, colWidths=[2*inch, 1*inch, 1*inch, 1*inch, 1*inch, 1.2*inch])
        att_table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#1a73e8')),
            ('TEXTCOLOR', (0, 0), (-1, 0), colors.whitesmoke),
            ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
            ('GRID', (0, 0), (-1, -1), 1, colors.black),
        ]))
        story.append(att_table)
    
    doc.build(story)
    return output_file

if __name__ == "__main__":
    report_type = sys.argv[1] if len(sys.argv) > 1 else ""
    
    if report_type == "student":
        student_id = sys.argv[2]
        output = f"reports/student_{student_id}_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
        generate_student_report(student_id, output)
        print(output)
    elif report_type == "attendance":
        class_id = sys.argv[2]
        output = f"reports/attendance_class_{class_id}_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
        generate_attendance_summary(class_id, output)
        print(output)
    else:
        print("Usage: python generate_report.py [student|attendance] [id]")
    
    cursor.close()
    db.close()