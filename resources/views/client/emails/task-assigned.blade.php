<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Notification - {{ config('app.name') }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f7f9fc;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        .content {
            padding: 30px;
        }
        .task-card {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .task-detail {
            margin-bottom: 10px;
        }
        .label {
            font-weight: 600;
            color: #495057;
            min-width: 120px;
            display: inline-block;
        }
        .value {
            color: #212529;
        }
        .priority-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .priority-low { background-color: #d1ecf1; color: #0c5460; }
        .priority-medium { background-color: #fff3cd; color: #856404; }
        .priority-high { background-color: #f8d7da; color: #721c24; }
        .priority-critical { background-color: #dc3545; color: white; }
        .btn-view {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 15px;
        }
        .footer {
            background-color: #f7f9fc;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>
                @if($type === 'assigned')
                    🎯 New Task Assigned
                @else
                    ✅ Task Created Successfully
                @endif
            </h1>
        </div>
        
        <div class="content">
            @if($type === 'assigned')
                <p>Hello <strong>{{ $toUser->name }}</strong>,</p>
                <p>You have been assigned a new task by <strong>{{ $fromUser->name }}</strong>.</p>
            @else
                <p>Hello <strong>{{ $toUser->name }}</strong>,</p>
                <p>You have successfully created and assigned a new task.</p>
            @endif
            
            <div class="task-card">
                <h3 style="margin-top: 0; color: #495057;">{{ $task->title }}</h3>
                
                <div class="task-detail">
                    <span class="label">Task Code:</span>
                    <span class="value">{{ $task->task_code }}</span>
                </div>
                
                <div class="task-detail">
                    <span class="label">Description:</span>
                    <span class="value">{{ $task->description }}</span>
                </div>
                
                <div class="task-detail">
                    <span class="label">Deadline:</span>
                    <span class="value">{{ \Carbon\Carbon::parse($task->deadline_date)->format('d M Y') }}</span>
                </div>
                
                <div class="task-detail">
                    <span class="label">Priority:</span>
                    <span class="priority-badge priority-{{ $task->priority }}">
                        {{ ucfirst($task->priority) }}
                    </span>
                </div>
                
                <div class="task-detail">
                    <span class="label">Status:</span>
                    <span class="value" style="text-transform: capitalize;">{{ $task->status }}</span>
                </div>
                
                @if($type === 'assigned')
                <div class="task-detail">
                    <span class="label">Assigned By:</span>
                    <span class="value">{{ $fromUser->name }} ({{ $fromUser->employee_id }})</span>
                </div>
                @endif
            </div>
            
            <!--<p>-->
            <!--    <a href="{{ url('/tasks/' . $task->id) }}" class="btn-view">-->
            <!--        @if($type === 'assigned')-->
            <!--            View Task Details-->
            <!--        @else-->
            <!--            View Created Task-->
            <!--        @endif-->
            <!--    </a>-->
            <!--</p>-->
            
            @if($type === 'assigned')
            <p style="color: #6c757d; font-size: 14px;">
                Please review the task and update the status as you progress. The deadline is 
                <strong>{{ \Carbon\Carbon::parse($task->deadline_date)->format('F d, Y') }}</strong>.
            </p>
            @endif
        </div>
        
        <div class="footer">
            <p>This is an automated notification from {{ config('app.name') }}.</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>